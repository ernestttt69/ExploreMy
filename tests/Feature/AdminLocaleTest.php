<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminLocaleTest extends TestCase
{
    public function test_admin_uses_english_without_changing_customer_language(): void
    {
        $user = User::factory()->create(['preferred_language' => 'zh']);
        $this->actingAs($user)->withSession(['locale' => 'zh'])
            ->get(route('admin.login'))->assertOk()->assertSee('lang="en"', false);
        $this->assertSame('zh', session('locale'));
        $this->withSession(['admin_authenticated' => true])
            ->get(route('admin.attractions.index'))->assertOk()->assertSee('lang="en"', false);
        $this->assertSame('zh', session('locale'));
        $this->get(route('attractions.index'))->assertOk()->assertSee('lang="zh"', false);
        $this->assertSame('zh', $user->fresh()->preferred_language);
    }

    public function test_admin_login_ignores_a_guest_chinese_locale(): void
    {
        $this->withSession(['locale' => 'zh'])->get(route('admin.login'))
            ->assertOk()->assertSee('lang="en"', false);
        $this->assertSame('zh', session('locale'));
    }
}
