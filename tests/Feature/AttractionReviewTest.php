<?php
namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\State;
use Tests\TestCase;

class AttractionReviewTest extends TestCase
{
    public function test_removed_status_fields_do_not_hide_places(): void
    {
        $state = State::create(['state_name' => 'Johor']);
        $place = Attraction::create(['place_id' => 'review-test', 'state_id' => $state->getKey(),
            'attraction_name' => 'Review place', 'location' => 'Johor']);
        $payload = ['place_id' => 'review-test', 'state_id' => $state->getKey(),
            'attraction_name' => 'Review place', 'location' => 'Johor',
            'entrance_fee' => 'Free', 'budget_level' => 'Free',
            'source_url' => 'https://example.com/place', 'mark_verified' => 1, 'is_visible' => 0];
        $this->withSession(['admin_authenticated' => true])->putJson(route('admin.attractions.update', $place), $payload)->assertOk();
        $this->assertNull($place->fresh()->verified_at);
        $this->get(route('attractions.index'))->assertOk()->assertViewHas('attractions', fn ($rows) => $rows->total() === 1);
        $this->get(route('attractions.show', $place->getKey()))->assertOk();
        $this->get(route('admin.attractions.edit', $place->getKey()))->assertOk()->assertDontSee('Source and review')->assertDontSee('Display status');
        $this->get(route('admin.attractions.index', ['review' => 'due']))->assertOk()
            ->assertViewHas('attractions', fn ($rows) => $rows->total() === 1);
        $payload['source_url'] = '';
        $this->putJson(route('admin.attractions.update', $place->getKey()), $payload)->assertOk();
    }
}
