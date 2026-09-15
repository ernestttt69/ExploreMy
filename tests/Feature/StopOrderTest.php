<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\SavedPlaceCollection;
use App\Models\State;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\StopOrderOptimizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StopOrderTest extends TestCase
{
    private function matrix(array $path): array
    {
        $matrix = array_fill(0, 3, array_fill(0, 3, 100));
        foreach ([0, 1, 2] as $index) $matrix[$index][$index] = 0;
        $matrix[$path[0]][$path[1]] = 1;
        $matrix[$path[1]][$path[2]] = 1;
        return $matrix;
    }

    public function test_each_preference_optimizes_its_own_metric(): void
    {
        $metrics = ['durations' => $this->matrix([0, 2, 1]), 'distances' => $this->matrix([1, 0, 2]), 'fares' => $this->matrix([2, 1, 0])];
        $optimizer = app(StopOrderOptimizer::class);
        $this->assertSame([0, 2, 1], $optimizer->optimize($metrics, 'fastest'));
        $this->assertSame([1, 0, 2], $optimizer->optimize($metrics, 'shortest'));
        $this->assertSame([2, 1, 0], $optimizer->optimize($metrics, 'lowest_cost'));
        $metrics['fares'][2][1] = null;
        $this->assertNotSame([2, 1, 0], $optimizer->optimize($metrics, 'lowest_cost'));
    }

    private function fakeRoutes(): void
    {
        config(['services.google_maps.routes_api_key' => 'test']);
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'computeRouteMatrix')) {
                $rows = [];
                $matrix = $this->matrix([0, 2, 1]);
                foreach ([0, 1, 2] as $a) {
                    foreach ([0, 1, 2] as $b) {
                        $rows[] = ['originIndex' => $a, 'destinationIndex' => $b, 'condition' => 'ROUTE_EXISTS', 'distanceMeters' => $matrix[$a][$b] * 100, 'duration' => ($matrix[$a][$b] * 60).'s'];
                    }
                }
                return Http::response($rows);
            }
            return Http::response(['routes' => [[
                'duration' => '60s', 'distanceMeters' => 100,
                'travelAdvisory' => ['transitFare' => ['currencyCode' => 'MYR', 'units' => '3']],
                'legs' => [['steps' => [['travelMode' => 'WALK', 'staticDuration' => '60s', 'polyline' => ['encodedPolyline' => 'test']]]]],
            ]]]);
        });
    }

    public function test_normal_auto_order_and_manual_order_survive_mode_switches(): void
    {
        $this->fakeRoutes();
        $this->actingAs(User::factory()->create());
        $keys = ['catalog:0', 'catalog:1', 'catalog:2'];
        $payload = ['optimization_preference' => 'fastest', 'destination_keys' => $keys, 'start_time' => '09:00'];
        $this->post(route('route.preference'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([$keys[0], $keys[2], $keys[1]], array_column(session('routeResult.stops'), 'route_key'));
        $transit = collect(session('routeOptions'))->firstWhere('travel_mode', 'TRANSIT');
        $this->assertSame(6.0, $transit['total_fare']);
        foreach (['TRANSIT', 'DRIVE'] as $mode) {
            $this->post(route('route.preference'), $payload + ['order_mode' => 'manual', 'travel_mode' => $mode])->assertSessionHasNoErrors();
            $this->assertSame($keys, array_column(session('routeResult.stops'), 'route_key'));
            foreach (session('routeOptions') as $option) {
                $this->assertSame($keys, array_column($option['stops'], 'route_key'));
            }
            if ($mode === 'DRIVE') {
                $this->get(route('route.index'))->assertOk()->assertSee('MYR 6.00');
            }
        }
    }

    public function test_collection_accepts_extra_saved_stop_and_preserves_manual_sequence(): void
    {
        $this->fakeRoutes();
        $user = User::factory()->create();
        $this->actingAs($user);
        $state = State::create(['state_name' => 'Johor']);
        $collection = SavedPlaceCollection::create(['user_id' => $user->getKey(), 'name' => 'Trip', 'start_date' => today()->addDay(), 'end_date' => today()->addDays(3), 'start_time' => '09:00', 'end_time' => '21:00']);
        $keys = [];
        foreach (['A', 'B', 'Extra'] as $index => $name) {
            $attraction = Attraction::create(['attraction_name' => $name, 'place_id' => $name, 'state_id' => $state->getKey(), 'location' => 'Johor']);
            $wishlist = Wishlist::create(['user_id' => $user->getKey(), 'attraction_id' => $attraction->getKey()]);
            if ($index < 2) {
                $attributes = ['attraction_id' => $attraction->getKey()];
                if (Schema::hasColumn('saved_place_collection_items', 'wishlist_id')) $attributes['wishlist_id'] = $wishlist->getKey();
                $item = $collection->items()->create($attributes);
                $keys[] = 'collection:'.$item->getKey();
            } else {
                $keys[] = 'wishlist:'.$wishlist->getKey();
            }
        }
        $url = route('route.index', ['source' => 'saved', 'collection' => $collection->getKey()]);
        $this->get($url)->assertOk()->assertSee('Extra')->assertSee('data-move="up"', false)->assertSee('data-add-stop', false);
        $payload = ['source' => 'saved', 'collection_id' => $collection->getKey(), 'optimization_preference' => 'fastest', 'destination_keys' => $keys];
        $this->from($url)->post(route('route.preference'), $payload)->assertSessionHasNoErrors();
        $this->assertSame([$keys[0], $keys[2], $keys[1]], array_column(session('routeResult.stops'), 'route_key'));
        $this->from($url)->post(route('route.preference'), $payload + ['order_mode' => 'manual'])->assertSessionHasNoErrors();
        $this->assertSame($keys, array_column(session('routeResult.stops'), 'route_key'));
        $this->assertSame(2, $collection->items()->count());
        $east = State::create(['state_name' => 'Sabah']);
        $island = Attraction::create(['attraction_name' => 'Mabul Island', 'place_id' => 'mabul', 'state_id' => $east->getKey(), 'location' => 'Sabah']);
        $islandSave = Wishlist::create(['user_id' => $user->getKey(), 'attraction_id' => $island->getKey()]);
        // An island in the picker must not trigger either warning until it is selected.
        $this->get($url)->assertOk()->assertViewHas('requiresFlight', false)->assertViewHas('requiresFerry', false);
        session()->forget(['routeResult', 'routeOptions']);
        $this->withSession(['routeResult' => null, 'routeOptions' => null, '_old_input' => ['destination_keys' => [$keys[0], 'wishlist:'.$islandSave->getKey()]]])
            ->get($url)->assertOk()->assertViewHas('requiresFlight', true)->assertViewHas('requiresFerry', true);
        $this->withSession(['_old_input' => ['destination_keys' => [$keys[0], $keys[1]]]])
            ->get($url)->assertOk()->assertViewHas('requiresFlight', false)->assertViewHas('requiresFerry', false);
    }
}
