<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

class TransitDepartureSuggestion
{
    public function find(array $request, CarbonImmutable $departure): ?CarbonImmutable
    {
        $now = CarbonImmutable::now($departure->timezone);
        $first = $departure->isBefore($now)
            ? $now->addDay()->setTime($departure->hour, $departure->minute)
            : $departure->addHour();
        $candidates = [$first, $first->addDay()->setTime(9, 0)];

        foreach ($candidates as $candidate) {
            try {
                $route = Http::acceptJson()->withHeaders([
                    'X-Goog-Api-Key' => config('services.google_maps.routes_api_key'),
                    'X-Goog-FieldMask' => 'routes.duration,routes.legs',
                ])->connectTimeout(2)->timeout(4)->post(
                    'https://routes.googleapis.com/directions/v2:computeRoutes',
                    array_replace($request, ['departureTime' => $candidate->toRfc3339String()])
                )->throw()->json('routes.0');
                if (is_array($route) && !empty($route['legs']) && isset($route['duration'])) {
                    return $candidate;
                }
            } catch (Throwable $exception) {
                // Optional advice must not hide the original route failure.
                return null;
            }
        }

        return null;
    }
}
