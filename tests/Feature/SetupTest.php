<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\PreferenceCategory;
use Tests\TestCase;
class SetupTest extends TestCase
{
    public function test_new_user_completes_setup_once(): void
    {
        $user = User::factory()->create();
        $user->setup_required = true;
        $user->save();
        $category = PreferenceCategory::create(['category_name' => 'Nature']);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('setup.show'));
        $this->get(route('setup.show'))->assertOk();
        $this->post(route('setup.store'), ['preferred_language' => 'zh', 'preferences' => [$category->getKey()]])
            ->assertRedirect(route('dashboard'));
        $this->assertFalse((bool) $user->fresh()->setup_required);
        $this->assertSame('zh', $user->fresh()->preferred_language);
        $this->assertSame(1, $user->preferenceCategories()->count());
        $this->get(route('dashboard'))->assertOk()->assertSee('lang="zh"', false);
        $this->get(route('setup.show'))->assertRedirect(route('profile'));
    }
}
