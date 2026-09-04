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

        $context = $this->createMock(AttractionContextService::class);
        $context->method('retrieve')->willReturn([]);
        $this->app->instance(AttractionContextService::class, $context);
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
}
