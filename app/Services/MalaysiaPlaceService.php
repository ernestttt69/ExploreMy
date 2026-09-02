<?php

namespace App\Services;

use App\Models\ItineraryItem;
use App\Models\MalaysianPlace;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class MalaysiaPlaceService
{
    private const CACHE_HOURS = 24;

    /**
     * Search a verified Malaysian place catalogue backed by the geocoding provider.
     *
     * @return Collection<int, MalaysianPlace>
     */
    public function search(string $query, int $limit = 8): Collection
    {
        $normalizedQuery = trim(preg_replace('/\s+/', ' ', $query) ?? '');
        $cacheKey = 'malaysia-places:'.mb_strtolower($normalizedQuery).':'.$limit;

        return Cache::remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($normalizedQuery, $limit): Collection {
            try {
                $response = Http::acceptJson()
                    ->timeout((int) config('services.places.timeout', 3))
                    ->get((string) config('services.places.geocoding_endpoint'), [
                        'name' => $normalizedQuery,
                        'count' => min(max($limit, 1), 20),
                        'language' => 'en',
                        'countryCode' => 'MY',
                    ]);

                $response->throw();
                $results = $response->json('results', []);

                if (! is_array($results)) {
                    return collect();
                }

                return collect($results)
                    ->filter(fn ($result) => is_array($result) && ($result['country_code'] ?? null) === 'MY')
                    ->map(fn (array $result) => $this->persist($result))
                    ->values();
            } catch (ConnectionException $exception) {
                return $this->localSearch($normalizedQuery, $limit);
            } catch (Throwable $exception) {
                return $this->localSearch($normalizedQuery, $limit);
            }
        });
    }

    /**
     * Resolve and permanently link a legacy itinerary item to a Malaysian place.
     */
    public function resolveForItem(ItineraryItem $item): ?MalaysianPlace
    {
        if ($item->place !== null) {
            return $item->place;
        }

        $location = trim((string) $item->location);

        if ($location === '') {
            return null;
        }

        $place = $this->search($location, 1)->first();

        if (! $place instanceof MalaysianPlace) {
            return null;
        }

        $item->forceFill([
            'place_id' => $place->getKey(),
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'location' => $place->display_name,
        ])->save();
        $item->setRelation('place', $place);

        return $place;
    }

    /**
     * Get a selected place by identifier for the itinerary editor integration.
     */
    public function find(int $placeId): ?MalaysianPlace
    {
        return MalaysianPlace::query()->find($placeId);
    }

    /**
     * Persist provider data once so future itinerary items can reference it.
     *
     * @param array<string, mixed> $result
     */
    private function persist(array $result): MalaysianPlace
    {
        $providerPlaceId = (string) ($result['id'] ?? '');

        if ($providerPlaceId === '' || ! isset($result['latitude'], $result['longitude'], $result['name'])) {
            throw new \RuntimeException('The place provider returned an incomplete location result.');
        }

        return MalaysianPlace::query()->updateOrCreate(
            ['provider' => 'open-meteo', 'provider_place_id' => $providerPlaceId],
            [
                'name' => (string) $result['name'],
                'display_name' => $this->displayName($result),
                'admin1' => $result['admin1'] ?? null,
                'admin2' => $result['admin2'] ?? null,
                'country_code' => 'MY',
                'latitude' => (float) $result['latitude'],
                'longitude' => (float) $result['longitude'],
                'timezone' => $result['timezone'] ?? null,
                'provider_payload' => $result,
            ]
        );
    }

    /**
     * Return persisted Malaysian locations if the provider cannot be reached.
     *
     * @return Collection<int, MalaysianPlace>
     */
    private function localSearch(string $query, int $limit): Collection
    {
        return MalaysianPlace::query()
            ->where('country_code', 'MY')
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'like', $query.'%')
                    ->orWhere('display_name', 'like', $query.'%');
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Build the text displayed beside an itinerary stop.
     *
     * @param array<string, mixed> $result
     */
    private function displayName(array $result): string
    {
        return implode(', ', array_filter([
            $result['name'] ?? null,
            $result['admin1'] ?? null,
            'Malaysia',
        ]));
    }
}
