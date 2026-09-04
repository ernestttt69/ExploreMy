<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetItineraryWeatherRequest;
use App\Models\Attraction;
use App\Models\ItineraryItem;
use App\Models\MalaysianPlace;
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
            ->filter(fn ($item) => $item->scheduled_date !== null && $item->category !== 'transport')
            ->map(function ($item) use ($refresh): ?array {
                $place = $item->place;
                $providerPlaceId = $item->metadata['place_id'] ?? null;
                $attraction = $providerPlaceId
                    ? Attraction::with('state')->where('place_id', $providerPlaceId)->first()
                    : null;
                $areaCoordinates = $this->areaCoordinates($attraction?->state?->state_name);

                if ($item->latitude !== null && $item->longitude !== null) {
                    $latitude = (float) $item->latitude;
                    $longitude = (float) $item->longitude;
                } elseif ($areaCoordinates !== null) {
                    [$latitude, $longitude] = $areaCoordinates;
                } else {
                    $place = $this->resolvePlaceForItem($item, $attraction);

                    if ($place === null) {
                        return null;
                    }

                    $latitude = (float) $place->latitude;
                    $longitude = (float) $place->longitude;
                }

                $outdoor = $this->isOutdoor($item->category, $item->metadata);
                $forecast = $this->weatherService->getForecastForStop(
                    $latitude,
                    $longitude,
                    $item->scheduled_date->toDateString(),
                    $outdoor,
                    $refresh
                );

                return [
                    'stop_id' => $item->item_id,
                    'title' => $item->title,
                    'scheduled_date' => $item->scheduled_date->toDateString(),
                    'location' => $place?->display_name ?? $item->location ?? $item->title,
                    'place' => [
                        'place_id' => $place?->getKey(),
                        'name' => $place?->name ?? $item->title,
                        'display_name' => $place?->display_name ?? $item->location ?? $item->title,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ],
                    'is_outdoor' => $outdoor,
                    'weather' => $forecast,
                    'requires_weather_alert' => (bool) $forecast['is_adverse_alert'],
                    'indoor_alternatives' => $forecast['is_adverse_alert']
                        ? $this->indoorAlternatives($place?->admin1, $item->title)
                        : [],
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

    private function resolvePlaceForItem(ItineraryItem $item, ?Attraction $attraction = null): ?MalaysianPlace
    {
        $queries = array_values(array_unique(array_filter([
            $attraction?->location,
            $attraction?->state?->state_name,
            $item->location,
        ])));

        foreach ($queries as $query) {
            $place = $this->places->search($query, 1)->first();

            if ($place instanceof MalaysianPlace) {
                $item->forceFill([
                    'place_id' => $place->getKey(),
                    'latitude' => $place->latitude,
                    'longitude' => $place->longitude,
                ])->save();
                $item->setRelation('place', $place);

                return $place;
            }
        }

        return null;
    }

    /** @return array{0: float, 1: float}|null */
    private function areaCoordinates(?string $stateName): ?array
    {
        $areas = [
            'johor' => [1.4854, 103.7618],
            'kedah' => [6.1184, 100.3685],
            'kelantan' => [6.1254, 102.2381],
            'melaka' => [2.1896, 102.2501],
            'malacca' => [2.1896, 102.2501],
            'negeri sembilan' => [2.7258, 101.9424],
            'pahang' => [3.8077, 103.3260],
            'penang' => [5.4141, 100.3288],
            'pulau pinang' => [5.4141, 100.3288],
            'perak' => [4.5975, 101.0901],
            'perlis' => [6.4414, 100.1986],
            'sabah' => [5.9804, 116.0735],
            'sarawak' => [1.5533, 110.3592],
            'selangor' => [3.0738, 101.5183],
            'terengganu' => [5.3117, 103.1324],
            'kuala lumpur' => [3.1390, 101.6869],
            'putrajaya' => [2.9264, 101.6964],
            'labuan' => [5.2831, 115.2308],
        ];

        return $stateName ? ($areas[mb_strtolower(trim($stateName))] ?? null) : null;
    }

    /**
     * Suggest highly rated weather-safe attractions from the same state.
     * Culture, food and shopping preferences form the indoor catalogue.
     *
     * @return array<int, array<string, mixed>>
     */
    private function indoorAlternatives(?string $stateName, string $excludedTitle): array
    {
        if (! $stateName) {
            return [];
        }

        return Attraction::query()
            ->whereHas('state', fn ($query) => $query->where('state_name', $stateName))
            ->whereHas('preferences', fn ($query) => $query->whereIn('preference_categories.preference_id', [3, 5, 6]))
            ->where('attraction_name', '!=', $excludedTitle)
            ->orderByDesc('rating')
            ->limit(3)
            ->get(['attraction_id', 'attraction_name', 'location', 'rating'])
            ->map(fn (Attraction $attraction) => [
                'id' => $attraction->getKey(),
                'name' => $attraction->attraction_name,
                'location' => $attraction->location,
                'rating' => $attraction->rating,
                'url' => route('attractions.show', $attraction->getKey()),
            ])
            ->all();
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
