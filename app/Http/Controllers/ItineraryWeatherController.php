<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetItineraryWeatherRequest;
use App\Models\Trip;
use App\Services\MalaysiaPlaceService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class ItineraryWeatherController extends Controller
{
    public function __construct(
        private WeatherService $weatherService,
        private MalaysiaPlaceService $places,
    )
    {
    }

    /**
     * Return weather information for each geocoded, dated itinerary stop.
     */
    public function show(GetItineraryWeatherRequest $request): JsonResponse
    {
        $trip = Trip::query()
            ->ownedBy((int) $request->user()->getAuthIdentifier())
            ->with(['items.place', 'items' => fn ($query) => $query->orderBy('scheduled_date')->orderBy('sort_order')])
            ->findOrFail((int) $request->validated('itinerary_id'));
        $refresh = $request->boolean('refresh');
        $stops = $trip->items
            ->filter(fn ($item) => $item->scheduled_date !== null)
            ->map(function ($item) use ($refresh): ?array {
                $place = $this->places->resolveForItem($item);

                if ($place === null) {
                    return null;
                }

                $outdoor = $this->isOutdoor($item->category, $item->metadata);
                $forecast = $this->weatherService->getForecastForStop(
                    $place->latitude,
                    $place->longitude,
                    $item->scheduled_date->toDateString(),
                    $outdoor,
                    $refresh
                );

                return [
                    'stop_id' => $item->item_id,
                    'title' => $item->title,
                    'scheduled_date' => $item->scheduled_date->toDateString(),
                    'location' => $place->display_name,
                    'place' => [
                        'place_id' => $place->getKey(),
                        'name' => $place->name,
                        'display_name' => $place->display_name,
                        'latitude' => $place->latitude,
                        'longitude' => $place->longitude,
                    ],
                    'is_outdoor' => $outdoor,
                    'weather' => $forecast,
                    'requires_weather_alert' => (bool) $forecast['is_adverse_alert'],
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'itinerary_id' => $trip->getKey(),
            'available_stops' => $stops->count(),
            'alert_count' => $stops->where('requires_weather_alert', true)->count(),
            'stops' => $stops,
        ]);
    }

    /**
     * Identify activities that should receive outdoor rain alerts.
     *
     * @param array<string, mixed>|null $metadata
     */
    private function isOutdoor(string $category, ?array $metadata): bool
    {
        if (is_array($metadata) && array_key_exists('is_outdoor', $metadata)) {
            return (bool) $metadata['is_outdoor'];
        }

        return in_array($category, ['activity', 'sightseeing'], true);
    }
}
