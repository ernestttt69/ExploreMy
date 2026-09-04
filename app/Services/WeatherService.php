<?php

namespace App\Services;

use App\Models\WeatherCache;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class WeatherService
{
    private const CACHE_HOURS = 3;
    private const LIVE_FORECAST_DAYS = 14;

    /**
     * Get forecast information for one dated itinerary stop.
     *
     * @return array<string, mixed>
     */
    public function getForecastForStop(float $lat, float $lng, string $date, bool $isOutdoor = false, bool $refresh = false): array
    {
        $forecastDate = Carbon::parse($date)->startOfDay();
        $locationKey = $this->locationKey($lat, $lng);
        $cacheKey = sprintf('weather:%s,%s:%s', $lat, $lng, $forecastDate->toDateString());

        if (! $refresh && ($cached = Cache::get($cacheKey)) !== null) {
            return $this->applyAlert($cached, $isOutdoor);
        }

        $databaseCache = WeatherCache::query()
            ->where('location_key', $locationKey)
            ->whereDate('forecast_date', $forecastDate)
            ->first();

        if (! $refresh && $databaseCache !== null && $databaseCache->expires_at->isFuture()) {
            $payload = $databaseCache->payload;
            Cache::put($cacheKey, $payload, now()->addHours(self::CACHE_HOURS));

            return $this->applyAlert($payload, $isOutdoor);
        }

        try {
            $payload = $this->requestForecast($lat, $lng, $forecastDate);
            $this->persist($locationKey, $forecastDate, $payload);
            Cache::put($cacheKey, $payload, now()->addHours(self::CACHE_HOURS));

            return $this->applyAlert($payload, $isOutdoor);
        } catch (ConnectionException $exception) {
            return $this->fallback($databaseCache, $isOutdoor, $forecastDate);
        } catch (Throwable $exception) {
            return $this->fallback($databaseCache, $isOutdoor, $forecastDate);
        }
    }

    /**
     * Retrieve either a live forecast or a historical climate estimate.
     *
     * @return array<string, mixed>
     */
    private function requestForecast(float $lat, float $lng, Carbon $forecastDate): array
    {
        $historical = $forecastDate->isAfter(today()->addDays(self::LIVE_FORECAST_DAYS));
        $requestDate = $historical ? $forecastDate->copy()->subYear() : $forecastDate;
        $endpoint = $historical
            ? (string) config('services.weather.historical_endpoint')
            : (string) config('services.weather.endpoint');
        $query = [
            'latitude' => $lat,
            'longitude' => $lng,
            'timezone' => 'auto',
            'start_date' => $requestDate->toDateString(),
            'end_date' => $requestDate->toDateString(),
            'daily' => $historical
                ? 'temperature_2m_max,temperature_2m_min,precipitation_sum,weather_code'
                : 'temperature_2m_max,temperature_2m_min,precipitation_probability_max,relative_humidity_2m_mean,uv_index_max,weather_code',
        ];
        $apiKey = config('services.weather.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $query['apikey'] = $apiKey;
        }

        $response = Http::acceptJson()
            ->timeout((int) config('services.weather.timeout', 3))
            ->get($endpoint, $query);

        $response->throw();
        $daily = $response->json('daily');

        if (! is_array($daily) || empty($daily['time'])) {
            throw new \RuntimeException('Weather provider returned no daily forecast data.');
        }

        return [
            'available' => true,
            'date' => $forecastDate->toDateString(),
            'temperature_high' => $this->dailyValue($daily, 'temperature_2m_max'),
            'temperature_low' => $this->dailyValue($daily, 'temperature_2m_min'),
            'precipitation_probability' => $historical
                ? $this->historicalPrecipitationProbability($this->dailyValue($daily, 'precipitation_sum'))
                : $this->dailyValue($daily, 'precipitation_probability_max'),
            'humidity' => $this->dailyValue($daily, 'relative_humidity_2m_mean'),
            'uv_index' => $this->dailyValue($daily, 'uv_index_max'),
            'condition_code' => $this->dailyValue($daily, 'weather_code'),
            'condition' => $this->conditionLabel($this->dailyValue($daily, 'weather_code')),
            'is_historical_estimate' => $historical,
            'is_stale' => false,
        ];
    }

    /**
     * Store a fresh provider response in the durable cache table.
     *
     * @param array<string, mixed> $payload
     */
    private function persist(string $locationKey, Carbon $forecastDate, array $payload): void
    {
        WeatherCache::query()->updateOrCreate(
            ['location_key' => $locationKey, 'forecast_date' => $forecastDate->toDateString()],
            ['payload' => $payload, 'expires_at' => now()->addHours(self::CACHE_HOURS)]
        );
    }

    /**
     * Return the latest stale cache record or a predictable unavailable payload.
     *
     * @return array<string, mixed>
     */
    private function fallback(?WeatherCache $databaseCache, bool $isOutdoor, Carbon $forecastDate): array
    {
        if ($databaseCache !== null && is_array($databaseCache->payload)) {
            return $this->applyAlert(array_merge($databaseCache->payload, ['is_stale' => true]), $isOutdoor);
        }

        return [
            'available' => false,
            'date' => $forecastDate->toDateString(),
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
            'is_historical_estimate' => $forecastDate->isAfter(today()->addDays(self::LIVE_FORECAST_DAYS)),
            'is_stale' => false,
        ];
    }

    /**
     * Add item-specific alert information to a location forecast.
     *
     * @param array<string, mixed> $forecast
     * @return array<string, mixed>
     */
    private function applyAlert(array $forecast, bool $isOutdoor): array
    {
        $rainProbability = (float) ($forecast['precipitation_probability'] ?? 0);
        $conditionCode = (int) ($forecast['condition_code'] ?? 0);
        $isSevere = in_array($conditionCode, [65, 67, 75, 82, 86, 95, 96, 99], true);

        // Cached forecasts are shared across locales. Rebuild all human-readable
        // labels for the current request so a language switch never shows stale text.
        $forecast['condition'] = $forecast['condition_code'] === null
            ? null
            : $this->conditionLabel($forecast['condition_code']);
        $forecast['is_severe'] = $isSevere;
        $forecast['is_adverse_alert'] = $isOutdoor && ($rainProbability > 60 || $isSevere);
        $forecast['alert_reason'] = match (true) {
            ! $forecast['is_adverse_alert'] => null,
            $isSevere => __('itinerary.severe_reason'),
            default => __('itinerary.rain_reason', ['probability' => (int) $rainProbability]),
        };

        return $forecast;
    }

    /**
     * Get the only daily value returned for a single-date provider request.
     *
     * @param array<string, mixed> $daily
     */
    private function dailyValue(array $daily, string $key): float|int|null
    {
        $value = $daily[$key][0] ?? null;

        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * Estimate a rain likelihood from historical daily rainfall totals.
     */
    private function historicalPrecipitationProbability(float|int|null $precipitation): int
    {
        return match (true) {
            $precipitation === null => 0,
            $precipitation >= 15 => 75,
            $precipitation >= 5 => 55,
            $precipitation > 0 => 30,
            default => 0,
        };
    }

    /**
     * Map Open-Meteo WMO weather codes to short frontend labels.
     */
    private function conditionLabel(float|int|null $code): ?string
    {
        return match ((int) $code) {
            0 => __('itinerary.conditions.clear'),
            1, 2 => __('itinerary.conditions.partly_cloudy'),
            3 => __('itinerary.conditions.overcast'),
            45, 48 => __('itinerary.conditions.fog'),
            51, 53, 55, 56, 57 => __('itinerary.conditions.drizzle'),
            61, 63, 65, 66, 67, 80, 81, 82 => __('itinerary.conditions.rain'),
            71, 73, 75, 77, 85, 86 => __('itinerary.conditions.snow'),
            95, 96, 99 => __('itinerary.conditions.thunderstorm'),
            default => null,
        };
    }

    /**
     * Create a stable, rounded cache key for a coordinate pair.
     */
    private function locationKey(float $lat, float $lng): string
    {
        return number_format($lat, 4, '.', '').','.number_format($lng, 4, '.', '');
    }
}
