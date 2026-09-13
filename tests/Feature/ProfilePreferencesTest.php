<?php

namespace Tests\Feature;

use App\Models\PreferenceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProfilePreferencesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invalid_profile_values_return_localized_errors(): void
    {
        foreach (['en', 'ms', 'zh'] as $locale) {
            $user = User::factory()->create(['preferred_language' => $locale]);
            $response = $this->actingAs($user)->postJson(route('profile.update'), [
                'name' => '', 'phone' => 'invalid!', 'preferred_language' => $locale,
            ]);
            $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone']);
            $this->assertSame(trans('profile_errors.phone', [], $locale), $response->json('errors.phone.0'));
            $this->actingAs($user)->get(route('profile'))->assertOk()
                ->assertSee('id="profile-save-errors"', false)
                ->assertSee(trans('profile_errors.failed', [], $locale));
        }
    }

    public function test_profile_displays_and_saves_place_preferences_without_unused_fields(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'en',
        ]);
        $nature = PreferenceCategory::query()->firstOrCreate(
            ['preference_id' => 1],
            ['category_name' => 'Nature']
        );
        $food = PreferenceCategory::query()->firstOrCreate(
            ['preference_id' => 5],
            ['category_name' => 'Food & Drinks']
        );

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertSee('name="preferences[]"', false)
            ->assertDontSee('name="nationality"', false)
            ->assertDontSee('name="bio"', false);

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name,
            'preferred_language' => 'en',
            'personalisation_consent' => '1',
            'preferences' => [$nature->preference_id, $food->preference_id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$nature->preference_id, $food->preference_id],
            $user->preferenceCategories()->pluck('preference_categories.preference_id')->all()
        );
    }

}
