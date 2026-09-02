<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Models\ItineraryItem;
use App\Models\MalaysianPlace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItineraryWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_can_add_save_and_export_an_itinerary_item(): void
    {
        $user = User::factory()->create();
        $trip = Trip::query()->create([
            'user_id' => $user->user_id,
            'title' => 'Penang weekend',
            'destination' => 'Penang',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'days' => 2,
            'co2_kg' => 0,
            'map_center' => [5.4141, 100.3288],
        ]);

        $this->actingAs($user)
            ->postJson(route('itineraries.items.store', $trip), [
                'category' => 'sightseeing',
                'title' => 'George Town heritage walk',
                'scheduled_date' => today()->toDateString(),
                'location' => 'George Town, Penang',
                'latitude' => 5.4141,
                'longitude' => 100.3288,
            ])
            ->assertCreated()
            ->assertJsonPath('item.title', 'George Town heritage walk');

        $this->actingAs($user)
            ->postJson(route('itineraries.save', $trip))
            ->assertOk()
            ->assertJsonPath('message', 'Itinerary saved to cloud storage.');

        $this->actingAs($user)
            ->get(route('itineraries.export.pdf', $trip))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_generated_share_link_is_view_only(): void
    {
        $user = User::factory()->create();
        $trip = Trip::query()->create([
            'user_id' => $user->user_id,
            'title' => 'Kuala Lumpur day trip',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'days' => 1,
            'co2_kg' => 0,
            'map_center' => [3.1390, 101.6869],
        ]);

        $this->actingAs($user)
            ->postJson(route('itineraries.share', $trip), ['permission' => 'edit'])
            ->assertUnprocessable();

        $response = $this->actingAs($user)
            ->postJson(route('itineraries.share', $trip), ['permission' => 'view'])
            ->assertCreated()
            ->assertJsonPath('permission', 'view');

        $token = basename((string) $response->json('url'));

        $this->get(route('itineraries.shared', ['token' => $token]))
            ->assertOk()
            ->assertSee('View-only link')
            ->assertDontSee('Save changes');
    }

    public function test_each_dated_destination_returns_its_weather_forecast(): void
    {
        Http::fake([
            '*' => Http::response([
                'daily' => [
                    'time' => [today()->toDateString()],
                    'temperature_2m_max' => [31],
                    'temperature_2m_min' => [24],
                    'precipitation_probability_max' => [40],
                    'relative_humidity_2m_mean' => [79],
                    'uv_index_max' => [7],
                    'weather_code' => [2],
                ],
            ]),
        ]);
        $user = User::factory()->create();
        $trip = Trip::query()->create([
            'user_id' => $user->user_id,
            'title' => 'Penang forecast',
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'days' => 1,
            'co2_kg' => 0,
        ]);
        $place = MalaysianPlace::query()->create([
            'provider' => 'test',
            'provider_place_id' => 'penang-test',
            'name' => 'George Town',
            'display_name' => 'George Town, Penang, Malaysia',
            'country_code' => 'MY',
            'latitude' => 5.4141,
            'longitude' => 100.3288,
            'provider_payload' => [],
        ]);
        $item = ItineraryItem::query()->create([
            'trip_id' => $trip->id,
            'place_id' => $place->id,
            'category' => 'sightseeing',
            'title' => 'Street art walk',
            'scheduled_date' => today()->toDateString(),
            'location' => $place->display_name,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->getJson(route('itinerary.weather', ['itinerary_id' => $trip->id]))
            ->assertOk()
            ->assertJsonPath('available_stops', 1)
            ->assertJsonPath('stops.0.stop_id', $item->item_id)
            ->assertJsonPath('stops.0.scheduled_date', today()->toDateString())
            ->assertJsonPath('stops.0.weather.available', true);
    }
}
