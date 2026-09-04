<?php

namespace Tests\Feature;

use App\Models\State;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AttractionSearchValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_filter_can_be_submitted_without_search_text(): void
    {
        $state = State::create(['state_name' => 'Search Test State']);

        $this->get(route('attractions.index', [
            'search_submitted' => '1',
            'state_id' => $state->state_id,
        ]))->assertOk()->assertSessionDoesntHaveErrors();
    }

    public function test_completely_empty_search_is_rejected(): void
    {
        $this->from(route('attractions.index'))
            ->get(route('attractions.index', ['search_submitted' => '1']))
            ->assertRedirect(route('attractions.index'))
            ->assertSessionHasErrors('search');
    }
}
