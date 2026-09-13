<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AiChatService;
use Tests\TestCase;

class ChatbotSessionTest extends TestCase
{
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
