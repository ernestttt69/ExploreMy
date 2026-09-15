<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\PreferenceCategory;
use App\Models\State;
use App\Models\User;
use Tests\TestCase;

class ExploreClearFiltersTest extends TestCase
{
    public function test_clearing_empty_fields_returns_an_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('attractions.index', ['clear' => '1', 'search' => '   ']))
            ->assertRedirect(route('attractions.index'))
            ->assertSessionHasErrors('search')
            ->assertSessionMissing('success');
    }
    public function test_empty_or_whitespace_search_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['', '   '] as $search) {
            $this->get(route('attractions.index', [
                'search_submitted' => '1', 'search' => $search,
                'state_id' => '', 'budget_level' => '', 'rating' => '',
            ]))->assertRedirect(route('attractions.index'))->assertSessionHasErrors('search');
        }
        $this->get(route('attractions.index', ['search_submitted' => '1', 'search' => 'Park']))
            ->assertOk()->assertViewHas('searchSubmitted', true);
    }

    public function test_clearing_filters_restores_preferences_or_all_places_when_unset(): void
    {
        $user = User::factory()->create();
        $nature = PreferenceCategory::create(['category_name' => 'Nature']);
        $city = PreferenceCategory::create(['category_name' => 'City']);
        $state = State::create(['state_name' => 'Johor']);
        $places = [];
        foreach ([$nature, $city] as $category) {
            $place = Attraction::create([
                'place_id' => 'clear-filter-'.$category->getKey(),
                'state_id' => $state->getKey(),
                'attraction_name' => $category->category_name.' Place',
                'location' => 'Johor',
            ]);
            $place->preferences()->attach($category->getKey());
            $places[] = $place;
        }
        $user->preferenceCategories()->attach($nature->getKey());
        $this->actingAs($user)->get(route('attractions.index', [
            'search_submitted' => '1', 'categories' => [$city->getKey()],
        ]))->assertOk()->assertViewHas('attractions', fn ($rows) =>
            $rows->pluck('attraction_id')->all() === [$places[1]->getKey()]);

        $this->get(route('attractions.index', [
            'clear' => '1', 'search_submitted' => '1', 'categories' => [$city->getKey()],
        ]))->assertRedirect(route('attractions.index'))
            ->assertSessionHas('success', __('explore.filters_cleared'));
        $this->get(route('attractions.index'))->assertOk()
            ->assertViewHas('searchSubmitted', false)
            ->assertViewHas('attractions', fn ($rows) =>
                $rows->pluck('attraction_id')->all() === [$places[0]->getKey()])
            ->assertSee('id="filterSuccessPopup"', false)
            ->assertDontSee('id="searchSuccessPopup"', false);

        $user->preferenceCategories()->detach();
        $this->get(route('attractions.index', ['clear' => '1']))
            ->assertRedirect(route('attractions.index'));
        $this->get(route('attractions.index'))->assertOk()
            ->assertViewHas('attractions', fn ($rows) => $rows->total() === 2);
    }
}
