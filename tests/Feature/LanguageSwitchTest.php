<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    public function test_guest_can_switch_language(): void
    {
        $this->from(route('explore'))->post(route('language.update'), ['language' => 'zh'])
            ->assertRedirect(route('explore'))->assertSessionHas('locale', 'zh');
        $this->get(route('explore'))->assertOk()->assertSee('lang="zh"', false)
            ->assertSee('class="nav-language-switcher"', false);
        $this->post(route('language.update'), ['language' => 'invalid'])->assertSessionHasErrors('language');
        $this->assertSame('zh', session('locale'));
    }

    public function test_user_choice_is_saved_and_profile_preserves_it(): void
    {
        $user = User::factory()->create(['preferred_language' => 'en']);
        $this->actingAs($user)->postJson(route('profile.update'), ['preferred_language' => 'ms'])->assertOk()->assertJsonPath('redirect', route('profile'));
        $this->assertSame('ms', $user->fresh()->preferred_language);
        $this->get(route('profile'))->assertOk()->assertSee('lang="ms"', false)
            ->assertSee('name="preferred_language"', false)->assertDontSee('class="nav-language-switcher"', false);
        $this->postJson(route('profile.update'), ['name' => 'Updated name'])->assertOk();
        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertSame('ms', $user->fresh()->preferred_language);
        $this->get(route('admin.login'))->assertOk()->assertSee('lang="en"', false);
    }
}
