<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Trip;
use App\Services\DailyItineraryScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UnexpectedValueException;

class DailyItineraryTest extends TestCase
{
    private function stops(): array
    {
        return array_map(fn ($name) => ['name' => $name, 'suggested_visit_minutes' => 120, 'suggested_visit_display' => '2 hours'], ['A', 'B']);
    }

    private function journey(array $from, array $to, CarbonImmutable $departure): array
    {
        return ['duration_seconds' => 3600, 'departure_time' => $departure->format('g:i A')];
    }

    public function test_final_visit_moves_to_next_day_and_exact_cutoff_is_allowed(): void
    {
        $result = app(DailyItineraryScheduler::class)->schedule($this->stops(), CarbonImmutable::parse('2027-01-01 18:00'), '18:00', '21:00', null, $this->journey(...));
        $this->assertSame('2027-01-01T21:00:00+08:00', $result['transit_legs'][0]['arrival_at']);
        $this->assertSame('2027-01-02T18:00:00+08:00', $result['stops'][1]['visit_start_at']);
        $this->assertSame('2027-01-02', $result['end']->toDateString());
    }

    public function test_journey_is_requeried_at_next_day_departure(): void
    {
        $departures = [];
        $result = app(DailyItineraryScheduler::class)->schedule($this->stops(), CarbonImmutable::parse('2027-01-01 18:00'), '18:00', '20:30', null,
            function ($from, $to, $departure) use (&$departures) {
                $departures[] = $departure->format('Y-m-d H:i');
                return $this->journey($from, $to, $departure);
            });
        $this->assertSame(['2027-01-01 20:00', '2027-01-02 18:00'], $departures);
        $this->assertSame('2027-01-02', $result['transit_legs'][0]['trip_date']);
        $this->assertSame('2027-01-03T18:00:00+08:00', $result['stops'][1]['visit_start_at']);
    }

    public function test_collection_cannot_overflow_its_final_date(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(__('schedule.trip_full'));
        app(DailyItineraryScheduler::class)->schedule($this->stops(), CarbonImmutable::parse('2027-01-01 18:00'), '18:00', '21:00', CarbonImmutable::parse('2027-01-01'), $this->journey(...));
    }

    public function test_activity_longer_than_daily_window_is_rejected(): void
    {
        $this->expectExceptionMessage(__('schedule.activity_too_long'));
        app(DailyItineraryScheduler::class)->schedule($this->stops(), CarbonImmutable::parse('2027-01-01 18:00'), '18:00', '19:00', null, $this->journey(...));
    }

    private function fakeRoutes(): void
    {
        config(['services.google_maps.routes_api_key' => 'test']);
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'computeRouteMatrix')) {
                $rows = [];
                foreach ([0, 1] as $from) {
                    foreach ([0, 1] as $to) {
                        $rows[] = ['originIndex' => $from, 'destinationIndex' => $to, 'condition' => 'ROUTE_EXISTS', 'distanceMeters' => 1000, 'duration' => '3600s'];
                    }
                }
                return Http::response($rows);
            }
            return Http::response(['routes' => [[
                'duration' => '3600s', 'distanceMeters' => 1000,
                'legs' => [['steps' => [['travelMode' => 'WALK', 'staticDuration' => '3600s', 'polyline' => ['encodedPolyline' => 'test']]]]],
            ]]]);
        });
    }

    public function test_collection_end_time_is_saved_and_date_limit_is_enforced(): void
    {
        // Legacy collection-independence migrations skip SQLite. Match the current MySQL schema.
        \Illuminate\Support\Facades\Schema::drop('saved_place_collection_items');
        \Illuminate\Support\Facades\Schema::create('saved_place_collection_items', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('collection_item_id');
            $table->unsignedInteger('collection_id');
            $table->unsignedInteger('attraction_id');
            $table->timestamps();
            $table->unique(['collection_id', 'attraction_id']);
            $table->foreign('collection_id')->references('collection_id')->on('saved_place_collections')->cascadeOnDelete();
            $table->foreign('attraction_id')->references('attraction_id')->on('attractions')->cascadeOnDelete();
        });
        $this->travelTo(CarbonImmutable::parse('2027-01-01 08:00'));
        $this->fakeRoutes();
        $user = User::factory()->create();
        $this->actingAs($user);
        $state = \App\Models\State::create(['state_name' => 'Johor']);
        $ids = [];
        $keys = [];
        foreach (['A', 'B'] as $name) {
            $place = \App\Models\Attraction::create(['place_id' => $name, 'attraction_name' => $name, 'location' => 'Johor', 'state_id' => $state->getKey()]);
            $ids[] = $place->getKey();
            $saved = \App\Models\Wishlist::create(['user_id' => $user->getKey(), 'attraction_id' => $place->getKey()]);
            $keys[] = 'wishlist:'.$saved->getKey();
        }
        $this->post(route('saved-places.collections.store'), [
            'name' => 'Evening trip', 'start_date' => '2027-01-01', 'end_date' => '2027-01-02',
            'start_time' => '18:00', 'end_time' => '21:00', 'attraction_ids' => $ids,
        ])->assertSessionHasNoErrors()->assertRedirect(route('saved-places.index'));
        $collection = \App\Models\SavedPlaceCollection::firstOrFail();
        foreach ([[], ['start_time' => '', 'end_time' => ''], ['end_time' => '21:00']] as $index => $times) {
            $name = 'Optional times '.$index;
            $this->post(route('saved-places.collections.store'), [
                'name' => $name, 'start_date' => '2027-01-01', 'end_date' => '2027-01-02', 'attraction_ids' => $ids,
            ] + $times)->assertSessionHasNoErrors()->assertRedirect(route('saved-places.index'));
            $optional = \App\Models\SavedPlaceCollection::where('name', $name)->firstOrFail();
            $this->assertNull($optional->start_time);
            $this->assertSame($times['end_time'] ?? null ?: null, $optional->end_time);
        }
        $this->assertSame('21:00', substr($collection->end_time, 0, 5));
        // Collection membership is independent of the general wishlist, and needs no selected keys.
        \App\Models\Wishlist::where('user_id', $user->getKey())->delete();
        $payload = ['source' => 'saved', 'collection_id' => $collection->getKey(), 'optimization_preference' => 'fastest'];
        $this->post(route('route.preference'), $payload)->assertSessionHasNoErrors()->assertSessionHas('routeResult.trip_end_time', '21:00');
        $this->assertCount(2, session('routeResult.stops'));
        $this->get(route('route.index', ['source' => 'saved', 'collection' => $collection->getKey()]))
            ->assertOk()->assertSee('value="21:00"', false)->assertSee('Day 1')->assertSee('Day 2')
            ->assertSee('data-add-stop', false)->assertSee('data-move="up"', false)
            ->assertDontSee(__('schedule.help'));
        $this->post(route('route.preference'), $payload + ['start_time' => '10:00', 'end_time' => '16:00'])
            ->assertSessionHasNoErrors()->assertSessionHas('routeResult.trip_start_time', '10:00')
            ->assertSessionHas('routeResult.trip_end_time', '16:00');
        $this->assertSame('10:00', \Carbon\CarbonImmutable::parse(session('routeResult.stops.0.visit_start_at'))->format('H:i'));
        $this->assertSame('18:00', substr($collection->fresh()->start_time, 0, 5));
        $this->assertSame('21:00', substr($collection->fresh()->end_time, 0, 5));
        $this->get(route('route.index', ['source' => 'saved', 'collection' => $collection->getKey()]))
            ->assertOk()->assertSee('value="10:00"', false)->assertSee('value="16:00"', false);
        $this->post(route('route.preference'), $payload + ['start_time' => '17:00', 'end_time' => '16:00'])
            ->assertSessionHasErrors('end_time');
        $collection->update(['end_date' => '2027-01-03']);
        $this->post(route('route.preference'), $payload)->assertSessionHasNoErrors();
        $this->get(route('route.index', ['source' => 'saved', 'collection' => $collection->getKey()]))
            ->assertOk()->assertSee('Day 3')->assertSee(__('schedule.free_day'));
        $collection->update(['end_date' => '2027-01-01']);
        $this->post(route('route.preference'), $payload)->assertSessionHasErrors('route');
        $this->travelBack();
    }

    public function test_blank_end_time_is_optional_and_invalid_time_is_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-01 08:00'));
        $this->fakeRoutes();
        $this->actingAs(User::factory()->create());
        $payload = ['destination_keys' => ['catalog:0', 'catalog:1'], 'optimization_preference' => 'fastest', 'start_time' => '09:00'];
        $this->post(route('route.preference'), $payload + ['end_time' => ''])->assertSessionHasNoErrors();
        $this->post(route('route.preference'), array_merge($payload, ['start_time' => '23:59', 'end_time' => '']))
            ->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('routeResult.trip_start_time', '23:59')
            ->assertSessionHas('routeResult.trip_end_date', '2027-01-02');
        $this->assertNull(session('routeResult.trip_end_time'));
        $this->post(route('route.preference'), $payload + ['end_time' => '08:00'])->assertSessionHasErrors('end_time');
        $this->travelBack();
    }

    public function test_normal_generation_and_saved_trip_keep_overnight_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-01 08:00'));
        $this->fakeRoutes();
        $this->actingAs(User::factory()->create())->post(route('route.preference'), [
            'optimization_preference' => 'fastest', 'destination_keys' => ['catalog:0', 'catalog:1'],
            'start_time' => '18:00', 'end_time' => '21:00',
        ])->assertSessionHasNoErrors()->assertSessionHas('routeResult.trip_end_date', '2027-01-02');
        $this->get(route('route.index'))->assertOk()->assertSee('Daily end time')
            ->assertSee('Day 1')->assertSee('Day 2')->assertDontSee(__('schedule.help'));
        $this->post(route('itineraries.store-generated-route'))->assertSessionHasNoErrors()->assertRedirect();
        $trip = Trip::firstOrFail();
        $this->assertSame('2027-01-02', $trip->end_date->toDateString());
        $visits = $trip->items()->where('category', 'sightseeing')->orderBy('sort_order')->get();
        $this->assertSame('18:00:00', $visits[0]->start_time);
        $this->assertSame('20:00:00', $visits[0]->end_time);
        $this->assertSame('2027-01-02', $visits[1]->scheduled_date->toDateString());
        $this->assertSame('18:00:00', $visits[1]->start_time);
        $this->assertSame('20:00:00', $visits[1]->end_time);
        $this->travelBack();
    }
}
