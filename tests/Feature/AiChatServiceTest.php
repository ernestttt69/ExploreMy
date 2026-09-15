<?php

namespace Tests\Feature;

use App\Services\AiChatService;
use App\Services\AttractionContextService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ai.provider' => 'ollama']);

        $context = $this->createMock(AttractionContextService::class);
        $context->method('retrieve')->willReturn([]);
        $this->app->instance(AttractionContextService::class, $context);
    }

    public function test_cloud_chat_sends_authenticated_history_and_cleans_reply(): void
    {
        config([
            'services.ai.provider' => 'chat_completions',
            'services.ai.endpoint' => 'https://ai.example.test/v1/chat/completions',
            'services.ai.api_key' => 'test-secret',
            'services.ai.model' => 'test-model',
        ]);
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '**Visit Melaka.**']]],
        ])]);

        $this->assertSame('Visit Melaka.', app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => 'Where should I go?'],
        ], 'en'));
        Http::assertSent(fn ($request) => $request->url() === 'https://ai.example.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request['model'] === 'test-model'
            && $request['messages'][0]['role'] === 'system'
            && $request['messages'][1]['content'] === 'Where should I go?');
    }

    public function test_cloud_chat_rejects_missing_configuration_without_a_request(): void
    {
        config(['services.ai.provider' => 'chat_completions', 'services.ai.api_key' => '']);
        Http::fake();
        try {
            app(AiChatService::class)->reply([['role' => 'user', 'content' => 'Hello']], 'en');
            $this->fail('Missing configuration should fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('AI_CONFIGURATION_MISSING_OR_INVALID', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_cloud_chat_does_not_expose_provider_error_body(): void
    {
        config([
            'services.ai.provider' => 'chat_completions',
            'services.ai.endpoint' => 'https://ai.example.test/v1/chat/completions',
            'services.ai.api_key' => 'test-secret',
            'services.ai.model' => 'test-model',
        ]);
        Http::fake(['*' => Http::response(['error' => 'sensitive provider details'], 401)]);
        $this->expectExceptionMessage('AI_HTTP_401');
        app(AiChatService::class)->reply([['role' => 'user', 'content' => 'Hello']], 'en');
    }

    public function test_it_sends_chat_history_to_ollama_and_returns_the_reply(): void
    {
        config([
            'services.ollama.endpoint' => 'http://127.0.0.1:11434',
            'services.ollama.model' => 'qwen3:4b-instruct',
        ]);
        Http::fake(['*' => Http::response([
            'message' => ['role' => 'assistant', 'content' => 'Visit Melaka.'],
            'done' => true,
        ])]);

        $reply = app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => 'Where should I go?'],
        ], 'en');

        $this->assertSame('Visit Melaka.', $reply);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://127.0.0.1:11434/api/chat'
                && $request['model'] === 'qwen3:4b-instruct'
                && $request['stream'] === false
                && $request['messages'][1]['content'] === 'Where should I go?';
        });
    }

    public function test_it_instructs_the_model_to_use_the_selected_language(): void
    {
        Http::fake(['*' => Http::response([
            'message' => ['role' => 'assistant', 'content' => '您好！'],
        ])]);

        app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => '你好'],
        ], 'zh');

        Http::assertSent(fn ($request): bool => str_contains(
            $request['messages'][0]['content'],
            'Simplified Chinese'
        ));
    }

    public function test_chinese_question_gets_a_chinese_prompt_even_when_interface_is_english(): void
    {
        Http::fake(['*' => Http::response([
            'message' => ['role' => 'assistant', 'content' => '可以，建议您参观马六甲。'],
        ])]);

        app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => '可以推荐马来西亚的景点吗？'],
        ], 'en');

        Http::assertSent(fn ($request): bool => str_contains(
            $request['messages'][0]['content'],
            'Reply entirely in Simplified Chinese'
        ));
    }

    public function test_it_adds_database_results_to_the_system_prompt(): void
    {
        $context = $this->createMock(AttractionContextService::class);
        $context->method('retrieve')->willReturn([
            ['name' => 'Johor Zoo', 'state' => 'Johor', 'rating' => 4.1],
        ]);
        $this->app->instance(AttractionContextService::class, $context);

        Http::fake(['*' => Http::response([
            'message' => ['role' => 'assistant', 'content' => 'Johor Zoo is in Johor.'],
        ])]);

        app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => 'Tell me about Johor Zoo'],
        ], 'en');

        Http::assertSent(fn ($request): bool => str_contains(
            $request['messages'][0]['content'],
            '"name": "Johor Zoo"'
        ) && str_contains(
            $request['messages'][0]['content'],
            'only source for claims about specific attractions'
        ));
    }

    public function test_it_removes_model_thinking_and_decodes_html_entities(): void
    {
        Http::fake(['*' => Http::response([
            'message' => [
                'role' => 'assistant',
                'content' => '<think>Internal reasoning that users should not see.</think> Hi!&#x20;How can I help?',
            ],
        ])]);

        $reply = app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => 'Hi'],
        ], 'en');

        $this->assertSame('Hi! How can I help?', $reply);
        $this->assertStringNotContainsString('reasoning', $reply);
        $this->assertStringNotContainsString('think', $reply);
    }

    public function test_it_rejects_unfinished_thinking_instead_of_exposing_it(): void
    {
        Http::fake(['*' => Http::response([
            'message' => ['content' => '<think>Unfinished internal notes'],
        ])]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OLLAMA_EMPTY_RESPONSE');
        app(AiChatService::class)->reply([['role' => 'user', 'content' => 'Hi']], 'en');
    }

    public function test_it_filters_encoded_thinking_before_returning_answer(): void
    {
        Http::fake(['*' => Http::response([
            'message' => ['content' => '&lt;think&gt;Internal notes&lt;/think&gt;Hello!'],
        ])]);
        $this->assertSame('Hello!', app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => 'Hi'],
        ], 'en'));
    }

    public function test_it_rejects_malformed_unclosed_thinking(): void
    {
        Http::fake(['*' => Http::response([
            'message' => ['content' => "think>\nHere's a thinking process:\nInternal draft"],
        ])]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OLLAMA_EMPTY_RESPONSE');
        app(AiChatService::class)->reply([['role' => 'user', 'content' => 'Penang']], 'en');
    }

    public function test_it_removes_markdown_bold_symbols_from_plain_text_replies(): void
    {
        Http::fake(['*' => Http::response([
            'message' => [
                'role' => 'assistant',
                'content' => 'Johor Zoo has a rating of **4.1** and is in **Johor Bahru**.',
            ],
        ])]);

        $reply = app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => 'Tell me about Johor Zoo'],
        ], 'en');

        $this->assertSame('Johor Zoo has a rating of 4.1 and is in Johor Bahru.', $reply);
    }

    public function test_tables_become_readable_numbered_items(): void
    {
        Http::fake(['*' => Http::response([
            'message' => ['content' => "Places:\n\n| Attraction | Rating |\n| --- | --- |\n| Park | 4.3 |\n| Museum | 4.5 |\n\nCheck hours before visiting."],
        ])]);
        $reply = app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => 'Penang'],
        ], 'en');
        $this->assertStringContainsString("1. Park\nRating: 4.3\n\n2. Museum\nRating: 4.5", $reply);
        $this->assertStringContainsString('Check hours before visiting.', $reply);
        $this->assertStringNotContainsString('|', $reply);
    }

    public function test_it_removes_italic_labels_without_removing_literal_asterisks(): void
    {
        Http::fake(['*' => Http::response([
            'message' => ['content' => "*Address:* Penang\n*Hours:* 9 AM\n*Rating:* 4.3 stars\n2 * 3 = 6"],
        ])]);
        $reply = app(AiChatService::class)->reply([
            ['role' => 'user', 'content' => 'Penang'],
        ], 'en');
        $this->assertSame("Address: Penang\nHours: 9 AM\nRating: 4.3 stars\n2 * 3 = 6", $reply);
    }
}
