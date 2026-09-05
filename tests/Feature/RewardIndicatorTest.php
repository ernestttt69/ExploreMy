<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RewardIndicatorTest extends TestCase
{
    public function test_collection_returns_updated_balance_and_history(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->withSession(['pending_reward_activities' => ['daily_login' => 1]])
            ->postJson(route('rewards.activity.collect'), ['activity' => 'daily_login'])
            ->assertOk()->assertJson(['points' => 10, 'availableLabel' => __('rewards.available', ['points' => '10'])]);
        $this->assertStringContainsString('+10', $response->json('historyHtml'));
        $this->assertStringContainsString(__('rewards.transaction_activities.daily_login'), $response->json('historyHtml'));
        $this->get(route('rewards'))->assertOk()->assertSee('data-shop-balance>'.__('rewards.available', ['points' => '10']), false);
    }

    public function test_purchase_returns_spending_history_and_remaining_balance(): void
    {
        $user = User::factory()->create();
        \App\Models\GreenWallet::create(['user_id' => $user->getKey(), 'points' => 200]);
        $item = \App\Models\GreenShopItem::create(['name' => 'Leaf Starter', 'description' => 'Test', 'price' => 100, 'exp_value' => 50, 'is_available' => true]);
        $response = $this->actingAs($user)->postJson(route('rewards.purchase', $item))->assertOk()
            ->assertJson(['points' => 100, 'availableLabel' => __('rewards.available', ['points' => '100'])]);
        $this->assertStringContainsString('-100', $response->json('historyHtml'));
    }

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
