<?php

namespace Tests\Feature;

use App\Models\Attraction;
use App\Models\State;
use App\Models\Trip;
use App\Models\User;
use App\Models\WeatherCache;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_successful_forecast_is_mapped_and_cached(): void
    {
        Http::fake([
            '*' => Http::response([
                'daily' => [
                    'time' => [today()->addDay()->toDateString()],
                    'temperature_2m_max' => [32.4],
                    'temperature_2m_min' => [24.1],
                    'precipitation_probability_max' => [35],
                    'relative_humidity_2m_mean' => [78],
                    'uv_index_max' => [8.5],
                    'weather_code' => [2],
                ],
            ]),
        ]);

        $service = app(WeatherService::class);
        $date = today()->addDay()->toDateString();
        $forecast = $service->getForecastForStop(3.1390, 101.6869, $date);
        $cachedForecast = $service->getForecastForStop(3.1390, 101.6869, $date);

        $this->assertTrue($forecast['available']);
        $this->assertSame(32.4, $forecast['temperature_high']);
        $this->assertSame(24.1, $forecast['temperature_low']);
        $this->assertSame(35, $forecast['precipitation_probability']);
        $this->assertFalse($forecast['is_historical_estimate']);
        $this->assertSame($forecast, $cachedForecast);
        Http::assertSentCount(1);
    }

    public function test_cached_weather_condition_uses_the_current_language(): void
    {
        $date = today()->addDay()->toDateString();
        Http::fake(['*' => Http::response(['daily' => [
            'time' => [$date], 'temperature_2m_max' => [31], 'temperature_2m_min' => [24],
            'precipitation_probability_max' => [52], 'relative_humidity_2m_mean' => [81],
            'uv_index_max' => [8.9], 'weather_code' => [51],
        ]])]);
        $service = app(WeatherService::class);

        app()->setLocale('zh');
        $this->assertSame('毛毛雨', $service->getForecastForStop(5.3, 103.1, $date)['condition']);

        app()->setLocale('en');
        $this->assertSame('Drizzle', $service->getForecastForStop(5.3, 103.1, $date)['condition']);
        Http::assertSentCount(1);
    }

    public function test_outdoor_stop_with_high_rain_probability_gets_an_adverse_alert(): void
    {
        Http::fake([
            '*' => Http::response([
                'daily' => [
                    'time' => [today()->addDays(2)->toDateString()],
                    'temperature_2m_max' => [29],
                    'temperature_2m_min' => [23],
                    'precipitation_probability_max' => [75],
                    'relative_humidity_2m_mean' => [85],
                    'uv_index_max' => [3],
                    'weather_code' => [63],
                ],
            ]),
        ]);

        $forecast = app(WeatherService::class)->getForecastForStop(5.4141, 100.3288, today()->addDays(2)->toDateString(), true);

        $this->assertTrue($forecast['available']);
        $this->assertTrue($forecast['is_adverse_alert']);
        $this->assertSame('Rain', $forecast['condition']);
    }

    public function test_timeout_returns_a_standardized_unavailable_forecast(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('Weather API timed out.');
        });

        $date = today()->addDays(3)->toDateString();
        $forecast = app(WeatherService::class)->getForecastForStop(1.4927, 103.7414, $date, true);

        $this->assertSame([
            'available' => false,
            'date' => $date,
            'temperature_high' => null,
            'temperature_low' => null,
            'precipitation_probability' => null,
            'humidity' => null,
            'uv_index' => null,
            'condition_code' => null,
            'condition' => null,
            'is_adverse_alert' => false,
            'is_severe' => false,
            'alert_reason' => null,
            'is_historical_estimate' => false,
            'is_stale' => false,
        ], $forecast);
    }

    public function test_itinerary_weather_uses_the_attraction_area_when_stop_coordinates_are_missing(): void
    {
        $date = today()->toDateString();
        Http::fake(['*' => Http::response(['daily' => [
            'time' => [$date], 'temperature_2m_max' => [31], 'temperature_2m_min' => [25],
            'precipitation_probability_max' => [30], 'relative_humidity_2m_mean' => [78],
            'uv_index_max' => [7], 'weather_code' => [2],
        ]])]);
        $user = User::factory()->create();
        $state = State::create(['state_name' => 'Terengganu']);
        $attraction = Attraction::create([
            'place_id' => 'google-place-setiu', 'state_id' => $state->state_id,
            'attraction_name' => 'Setiu Adventure Park', 'location' => 'Setiu, Terengganu',
            'entrance_fee' => 'Free', 'budget_level' => 'Free',
        ]);
        $trip = Trip::create([
            'user_id' => $user->user_id, 'title' => 'Area weather trip',
            'start_date' => $date, 'end_date' => $date, 'days' => 1, 'co2_kg' => 0,
        ]);
        $item = $trip->items()->create([
            'category' => 'sightseeing', 'title' => $attraction->attraction_name,
            'scheduled_date' => $date, 'location' => $attraction->attraction_name,
            'carbon_kg' => 0, 'sort_order' => 0, 'metadata' => ['place_id' => $attraction->place_id],
        ]);

        $this->actingAs($user)->getJson(route('itinerary.weather', ['itinerary_id' => $trip->id]))
            ->assertOk()
            ->assertJsonPath('available_stops', 1)
            ->assertJsonPath('stops.0.stop_id', $item->item_id)
            ->assertJsonPath('stops.0.weather.temperature_high', 31);

        Http::assertSent(fn ($request) => (float) $request['latitude'] === 5.3117 && (float) $request['longitude'] === 103.1324);
    }

    public function test_severe_weather_alerts_an_outdoor_stop_even_when_rain_probability_is_low(): void
    {
        Http::fake(['*' => Http::response(['daily' => [
            'time' => [today()->addDay()->toDateString()],
            'temperature_2m_max' => [30], 'temperature_2m_min' => [24],
            'precipitation_probability_max' => [20], 'relative_humidity_2m_mean' => [80],
            'uv_index_max' => [2], 'weather_code' => [95],
        ]])]);

        $forecast = app(WeatherService::class)->getForecastForStop(3.1, 101.7, today()->addDay()->toDateString(), true);

        $this->assertTrue($forecast['is_severe']);
        $this->assertTrue($forecast['is_adverse_alert']);
        $this->assertStringContainsString('Severe weather', $forecast['alert_reason']);
    }

    public function test_api_failure_falls_back_to_expired_database_cache(): void
    {
        $date = today()->addDays(4)->toDateString();
        WeatherCache::query()->create([
            'location_key' => '3.1000,101.7000',
            'forecast_date' => $date,
            'payload' => ['available' => true, 'date' => $date, 'precipitation_probability' => 70, 'condition_code' => 63],
            'expires_at' => now()->subHour(),
        ]);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $forecast = app(WeatherService::class)->getForecastForStop(3.1, 101.7, $date, true);

        $this->assertTrue($forecast['available']);
        $this->assertTrue($forecast['is_stale']);
        $this->assertTrue($forecast['is_adverse_alert']);
    }

    public function test_manual_refresh_bypasses_a_valid_cache(): void
    {
        $date = today()->addDay()->toDateString();
        $response = fn (int $temperature) => ['daily' => [
            'time' => [$date], 'temperature_2m_max' => [$temperature], 'temperature_2m_min' => [24],
            'precipitation_probability_max' => [10], 'relative_humidity_2m_mean' => [70],
            'uv_index_max' => [5], 'weather_code' => [1],
        ]];
        Http::fakeSequence()->push($response(30))->push($response(33));
        $service = app(WeatherService::class);

        $service->getForecastForStop(3.1, 101.7, $date);
        $refreshed = $service->getForecastForStop(3.1, 101.7, $date, false, true);

        $this->assertSame(33, $refreshed['temperature_high']);
        Http::assertSentCount(2);
    }

    public function test_far_future_date_uses_a_historical_climate_estimate(): void
    {
        $date = today()->addDays(30)->toDateString();
        Http::fake(['*' => Http::response(['daily' => [
            'time' => [today()->subYear()->addDays(30)->toDateString()],
            'temperature_2m_max' => [31], 'temperature_2m_min' => [24],
            'precipitation_sum' => [16], 'weather_code' => [63],
        ]])]);

        $forecast = app(WeatherService::class)->getForecastForStop(3.1, 101.7, $date, true);

        $this->assertTrue($forecast['is_historical_estimate']);
        $this->assertSame(75, $forecast['precipitation_probability']);
        $this->assertTrue($forecast['is_adverse_alert']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'archive-api.open-meteo.com'));
    }
}
