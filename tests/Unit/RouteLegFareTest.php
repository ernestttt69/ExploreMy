<?php

namespace Tests\Unit;

use App\Http\Controllers\RoutePlanningController;
use Illuminate\Support\Facades\Http;
use Tests\CreatesApplication;

class RouteLegFareTest extends \Illuminate\Foundation\Testing\TestCase
{
    use CreatesApplication;

    public function test_walking_legs_allow_two_bus_fares_to_total_two_ringgit(): void
    {
        Http::fakeSequence()
            ->push($this->route([$this->step('TRANSIT', 'BUS')]))
            ->push($this->route([$this->step('WALK')]))
            ->push($this->route([$this->step('TRANSIT', 'BUS')]));

        $legs = $this->legs(4);
        $this->assertSame([1.0, 0.0, 1.0], array_column($legs, 'fare'));
        $this->assertEquals(2, array_sum(array_column($legs, 'fare')));
        $this->assertTrue($legs[0]['fare_is_estimated']);
    }

    public function test_a_partly_unknown_transit_fare_is_not_a_complete_total(): void
    {
        Http::fakeSequence()->push($this->route([
            $this->step('TRANSIT', 'BUS'),
            $this->step('TRANSIT', 'FERRY'),
        ]));
        $this->assertNull($this->legs(2)[0]['fare']);
    }

    public function test_zero_api_fare_does_not_hide_a_bus_estimate(): void
    {
        $response = $this->route([$this->step('WALK'), $this->step('TRANSIT', 'BUS'), $this->step('WALK')]);
        $response['routes'][0]['travelAdvisory']['transitFare'] = ['currencyCode' => 'MYR', 'units' => '0'];
        Http::fakeSequence()->push($response);
        $leg = $this->legs(2)[0];
        $this->assertSame(1.0, $leg['fare']);
        $this->assertTrue($leg['fare_is_estimated']);
        $this->assertFalse($leg['is_walking_only']);
    }

    public function test_valid_route_without_step_polylines_is_accepted(): void
    {
        $step = $this->step('WALK');
        unset($step['polyline']);
        $response = $this->route([$step]);
        $response['routes'][0]['polyline']['encodedPolyline'] = 'overview';
        Http::fakeSequence()->push($response);
        $leg = $this->legs(2)[0];
        $this->assertSame(['overview'], $leg['encoded_polylines']);
        $this->assertSame(0.0, $leg['fare']);
        $this->assertTrue($leg['is_walking_only']);
    }

    public function test_empty_route_identifies_the_failed_segment(): void
    {
        Http::fakeSequence()->push(['routes' => []]);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('from Place 1 to Place 2');
        $this->legs(2);
    }

    private function legs(int $count): array
    {
        $places = array_map(fn ($i) => [
            'name' => 'Place '.$i, 'place_id' => 'place-'.$i,
        ], range(1, $count));
        $method = new \ReflectionMethod(RoutePlanningController::class, 'getTransitLegs');
        $method->setAccessible(true);
        return $method->invoke(app(RoutePlanningController::class), $places);
    }

    private function route(array $steps): array
    {
        return ['routes' => [[
            'duration' => '600s', 'distanceMeters' => 1000,
            'legs' => [['steps' => $steps]],
        ]]];
    }

    private function step(string $mode, string $vehicle = ''): array
    {
        return [
            'travelMode' => $mode, 'staticDuration' => '300s',
            'distanceMeters' => 500, 'polyline' => ['encodedPolyline' => 'test'],
            'transitDetails' => ['transitLine' => [
                'name' => 'Test line', 'vehicle' => ['type' => $vehicle],
            ]],
        ];
    }
}
