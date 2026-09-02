<?php

namespace Tests\Feature;

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
            'is_historical_estimate' => false,
            'is_stale' => false,
        ], $forecast);
    }
}
