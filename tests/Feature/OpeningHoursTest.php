<?php

namespace Tests\Feature;

use App\Services\DailyItineraryScheduler;
use App\Services\PlaceOpeningHours;
use App\Models\Attraction;
use App\Models\State;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UnexpectedValueException;

class OpeningHoursTest extends TestCase
{
    public function test_weekday_unicode_split_hours_closed_and_unknown(): void
    {
        $hours = app(PlaceOpeningHours::class);
        $monday = CarbonImmutable::parse('2027-01-04');
        $periods = $hours->intervals("Monday: 9:00\u{202f}AM\u{2009}–\u{2009}12:00 PM, 2:00 PM–6:00 PM\nTuesday: Closed", $monday);
        $this->assertCount(2, $periods);
        $this->assertSame('09:00', $periods[0][0]->format('H:i'));
        $this->assertSame('18:00', $periods[1][1]->format('H:i'));
        $this->assertSame([], $hours->intervals('Tuesday: Closed', $monday->addDay()));
        $this->assertNull($hours->intervals('By appointment', $monday));
        $this->assertNull($hours->intervals('9 AM–6 PM, holidays vary', $monday));
        $this->assertSame('2027-01-05', $hours->intervals('Open 24 hours', $monday)[0][1]->toDateString());
    }

    public function test_full_visit_waits_for_afternoon_and_overnight_period_is_supported(): void
    {
        $hours = app(PlaceOpeningHours::class);
        $arrival = CarbonImmutable::parse('2027-01-04 11:00');
        $slot = $hours->nextVisit(['name' => 'Museum', 'operating_hours' => '09:00–12:00, 14:00–18:00'], $arrival, 7200, '09:00', '21:00', $arrival->startOfDay());
        $this->assertSame('14:00', $slot['start']->format('H:i'));
        $night = $hours->nextVisit(['name' => 'Night cafe', 'operating_hours' => "Monday: 8:00 PM–2:00 AM\nTuesday: Closed"], $arrival->addDay()->setTime(0, 30), 3600, '00:00', '21:00', null);
        $this->assertSame('2027-01-05 00:30', $night['start']->format('Y-m-d H:i'));
    }

    public function test_closed_place_cannot_overflow_collection_end_date(): void
    {
        $this->expectException(UnexpectedValueException::class);
        app(DailyItineraryScheduler::class)->schedule([
            ['name' => 'Closed museum', 'operating_hours' => 'Monday: Closed', 'suggested_visit_minutes' => 120, 'suggested_visit_display' => '2 hours'],
        ], CarbonImmutable::parse('2027-01-04 09:00'), '09:00', '21:00', CarbonImmutable::parse('2027-01-04'), fn () => []);
    }

    public function test_blank_end_time_allows_overnight_visits_but_still_checks_opening_hours(): void
    {
        $hours = app(PlaceOpeningHours::class);
        $arrival = CarbonImmutable::parse('2027-01-04 23:59');
        $slot = $hours->nextVisit(['name' => 'Park', 'operating_hours' => 'Open 24 hours'], $arrival, 7200, '23:59', null, null);
        $this->assertSame($arrival->toIso8601String(), $slot['start']->toIso8601String());
        $slot = $hours->nextVisit(['name' => 'Museum', 'operating_hours' => '09:00–18:00'], $arrival, 7200, '23:59', null, null);
        $this->assertSame('2027-01-05 09:00', $slot['start']->format('Y-m-d H:i'));
    }

    public function test_generation_uses_hours_and_warns_about_manual_order_with_suggestion(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-04 08:00'));
        $state = State::create(['state_name' => 'Kuala Lumpur']);
        foreach (['KLCC' => '12:00–18:00', 'KL Tower' => '09:00–11:00'] as $name => $hours) {
            Attraction::create(['attraction_name' => $name, 'place_id' => $name, 'state_id' => $state->getKey(), 'location' => 'Kuala Lumpur', 'operating_hours' => $hours]);
        }
        config(['services.google_maps.routes_api_key' => 'test']);
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'computeRouteMatrix')) {
                $rows = [];
                foreach ([0, 1] as $a) foreach ([0, 1] as $b) {
                    $rows[] = ['originIndex' => $a, 'destinationIndex' => $b, 'condition' => 'ROUTE_EXISTS', 'distanceMeters' => 1000, 'duration' => '600s'];
                }
                return Http::response($rows);
            }
            return Http::response(['routes' => [['duration' => '600s', 'distanceMeters' => 1000,
                'legs' => [['steps' => [['travelMode' => 'WALK', 'staticDuration' => '600s', 'polyline' => ['encodedPolyline' => 'test']]]]],
            ]]]);
        });
        $this->actingAs(User::factory()->create());
        $payload = ['optimization_preference' => 'fastest', 'destination_keys' => ['catalog:0', 'catalog:1'], 'start_time' => '09:00', 'end_time' => '21:00'];
        $this->post(route('route.preference'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['KL Tower', 'KLCC'], array_column(session('routeResult.stops'), 'name'));
        $this->assertSame('12:00', CarbonImmutable::parse(session('routeResult.stops.1.visit_start_at'))->format('H:i'));
        $this->post(route('route.preference'), $payload + ['order_mode' => 'manual'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['KLCC', 'KL Tower'], array_column(session('routeResult.stops'), 'name'));
        $this->assertSame(['KL Tower', 'KLCC'], session('routeResult.opening_suggested_order'));
        $this->assertNotEmpty(session('routeResult.opening_conflicts'));
        $this->get(route('route.index'))->assertOk()->assertSee('To reduce waiting')->assertSee('12:00 PM');
        $collection = \App\Models\SavedPlaceCollection::create([
            'user_id' => auth()->id(), 'name' => 'Opening hours trip',
            'start_date' => '2027-01-04', 'end_date' => '2027-01-04', 'start_time' => '09:00', 'end_time' => '21:00',
        ]);
        foreach (Attraction::orderBy('attraction_id')->get() as $attraction) {
            $save = \App\Models\Wishlist::create(['user_id' => auth()->id(), 'attraction_id' => $attraction->getKey()]);
            $attributes = ['attraction_id' => $attraction->getKey()];
            if (\Illuminate\Support\Facades\Schema::hasColumn('saved_place_collection_items', 'wishlist_id')) $attributes['wishlist_id'] = $save->getKey();
            $collection->items()->create($attributes);
        }
        $collectionPayload = ['source' => 'saved', 'collection_id' => $collection->getKey(), 'optimization_preference' => 'fastest'];
        $this->post(route('route.preference'), $collectionPayload + ['order_mode' => 'manual'])->assertSessionHasErrors('route');
        $this->assertStringContainsString('We suggest this sequence', session('errors')->first('route'));
        $this->post(route('route.preference'), $collectionPayload)->assertSessionHasNoErrors();
        $this->assertSame(['KL Tower', 'KLCC'], array_column(session('routeResult.stops'), 'name'));
        $this->assertSame('2027-01-04', session('routeResult.trip_end_date'));
        $this->travelBack();
    }
}
