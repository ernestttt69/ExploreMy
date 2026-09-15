<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiChatService;
use Tests\TestCase;

class ChatbotSessionTest extends TestCase
{
    public function test_system_questions_use_ai_with_product_context_and_session_history(): void
    {
        config(['services.ai.provider' => 'ollama']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([
            'message' => ['content' => 'A contextual explanation.'],
        ])]);
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([trans('chatbot_help.items', [], 'en')[0]['question'], 'Can I generate an itinerary without creating a collection?'] as $question) {
            $this->postJson(route('chatbot.message'), [
                'messages' => [['role' => 'user', 'content' => $question]],
            ])->assertOk()->assertJsonPath('reply', 'A contextual explanation.')
                ->assertSessionHas('chatbot.history.1.content', 'A contextual explanation.');
        }
        \Illuminate\Support\Facades\Http::assertSentCount(2);
        \Illuminate\Support\Facades\Http::assertSent(fn ($request) =>
            str_contains($request['messages'][0]['content'], 'Collections versus itineraries')
            && str_contains($request['messages'][0]['content'], 'Collections are optional, not required')
            && str_contains($request['messages'][0]['content'], 'Generate Itinerary in the Start Your Trip Now section')
            && str_contains($request['messages'][0]['content'], 'acknowledge and correct the mistake')
            && str_contains($request['messages'][0]['content'], 'CURRENT USER SNAPSHOT')
            && str_contains($request['messages'][0]['content'], '"saved_place_count":0'));
    }
    public function test_chat_history_is_restored_and_cleared_on_logout(): void
    {
        $user = User::factory()->create();
        $this->mock(AiChatService::class, function ($mock) {
            $mock->shouldReceive('reply')->once()->andReturn('A cached travel reply.');
        });
        $this->actingAs($user)->postJson(route('chatbot.message'), [
            'messages' => [['role' => 'user', 'content' => 'Where can I visit?']],
        ])->assertOk()->assertSessionHas('chatbot.history', [
            ['role' => 'user', 'content' => 'Where can I visit?'],
            ['role' => 'assistant', 'content' => 'A cached travel reply.'],
        ]);
        $this->get(route('attractions.index'))->assertOk()
            ->assertSee('A cached travel reply.')
            ->assertSee('data-chat-question', false);
        $this->post(route('logout'))->assertRedirect()->assertSessionMissing('chatbot.history');
        $this->actingAs($user)->get(route('attractions.index'))->assertOk()
            ->assertDontSee('A cached travel reply.');
    }
}
