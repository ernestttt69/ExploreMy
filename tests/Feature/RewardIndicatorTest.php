<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RewardIndicatorTest extends TestCase
{
    public function test_async_reward_action_is_queued_in_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('rewards.activity'), ['activity' => 'export_guidance'])
            ->assertOk()
            ->assertJson(['queued' => true])
            ->assertSessionHas('pending_reward_activities.export_guidance', 1);
    }
}
