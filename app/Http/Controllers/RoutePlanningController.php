<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\SavedPlaceCollection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

class RoutePlanningController extends Controller
{
    private const PLACES = [
        ['name' => 'KLCC', 'latitude' => 3.1579, 'longitude' => 101.7116],
        ['name' => 'KL Tower', 'latitude' => 3.1528, 'longitude' => 101.7037],
        ['name' => 'Central Market', 'latitude' => 3.1457, 'longitude' => 101.6953],
        ['name' => 'Merdeka 118', 'latitude' => 3.1417, 'longitude' => 101.7007],
        ['name' => 'Pavilion Kuala Lumpur', 'latitude' => 3.1491, 'longitude' => 101.7133],
        ['name' => 'The Exchange TRX', 'latitude' => 3.1420, 'longitude' => 101.7185],
    ];

    public function index(Request $request)
    {
        $usingSavedPlaces = $request->query('source') === 'saved';
        $collection = null;
        $savedPlaces = collect();

        if ($usingSavedPlaces) {
            $collection = $this->collectionForCurrentUser($request->query('collection'));
            $savedPlaces = $collection
                ? $this->savedPlacesForCollection($collection)
                : $this->savedPlacesForCurrentUser();
        }
        $availablePlaces = $usingSavedPlaces
            ? $savedPlaces->take(8)->values()->map(fn ($wishlist): array => [
                'name' => $wishlist->attraction->attraction_name,
                'place_id' => $wishlist->attraction->place_id,
                'route_key' => 'wishlist:' . $wishlist->wishlist_id,
            ])->all()
            : array_map(
                fn (array $place, int $index): array => [
                    ...$place,
                    'route_key' => 'catalog:' . $index,
                ],
                self::PLACES,
                array_keys(self::PLACES)
            );

        return view('route-planning.route', [
            'googleMapsBrowserKey' => config('services.google_maps.browser_api_key'),
            'usingSavedPlaces' => $usingSavedPlaces,
            'savedPlaces' => $savedPlaces,
            'collection' => $collection,
            'availablePlaces' => $availablePlaces,
        ]);
    }

    public function storePreference(Request $request)
    {
        $validated = $request->validate([
            'optimization_preference' => [
                'required',
                'in:fastest,shortest,lowest_cost',
            ],
            'route_option_index' => [
                'nullable',
                'integer',
                'between:0,2',
            ],
            'destination_keys' => ['required', 'array', 'min:2', 'max:8'],
            'destination_keys.*' => ['required', 'string'],
            'source' => ['nullable', 'in:saved'],
            'collection_id' => ['nullable', 'integer'],
        ]);

        try {
            $places = $this->placesFromDestinationKeys(
                $validated['destination_keys'],
                ($validated['source'] ?? null) === 'saved',
                $validated['collection_id'] ?? null
            );
        } catch (UnexpectedValueException $exception) {
            return back()->withInput()->withErrors(['route' => $exception->getMessage()]);
        }

        if (empty(config('services.google_maps.routes_api_key'))) {
            return back()
                ->withInput()
                ->withErrors([
                    'route' => __('messages.route_api_missing'),
                ]);
        }

        try {
            $travelMode = 'TRANSIT';
            $omittedPlaces = [];
            $fallbackNotice = null;
            try {
                $metrics = $this->getGoogleTransitMetrics($places, $travelMode);
            } catch (\RuntimeException $exception) {
                $travelMode = 'DRIVE';
                $fallbackNotice = __('route.fallback');
                $metrics = $this->getGoogleTransitMetrics($places, $travelMode, true);
                [$places, $metrics, $omittedPlaces] = $this->connectedRouteSubset(
                    $places,
                    $metrics
                );

                if (count($places) < 2) {
                    throw new UnexpectedValueException(
                        __('route.connect_error')
                    );
                }
            }

            $routeOptions = $this->findOptimalRoutes(
                $places,
                $metrics,
                $validated['optimization_preference'],
                3,
                $travelMode
            );
            $selectedOptionIndex = (int) ($validated['route_option_index'] ?? 0);
            $routeResult = $routeOptions[$selectedOptionIndex] ?? $routeOptions[0];
            $routeResult['omitted_places'] = $omittedPlaces;
            $routeResult['fallback_notice'] = $fallbackNotice;
            $routeResult['transit_legs'] = $this->getTransitLegs(
                $routeResult['stops'],
                $travelMode
            );

            if ($routeResult['transit_legs'] !== []) {
                $routeResult['departure_time'] = $routeResult['transit_legs'][0]['departure_time'];
                $lastLegIndex = array_key_last($routeResult['transit_legs']);
                $routeResult['arrival_time'] = $routeResult['transit_legs'][$lastLegIndex]['arrival_time'];
                $routeResult['total_duration_display'] = $this->formatDuration(
                    array_sum(array_column($routeResult['transit_legs'], 'duration_seconds'))
                );
            }
        } catch (UnexpectedValueException $exception) {
            return back()
                ->withInput()
                ->withErrors(['route' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'route' => __('messages.route_calculation_failed'),
                ]);
        }

        $pendingRewards = session('pending_reward_activities', []);
        $pendingRewards['generate_itinerary'] = ($pendingRewards['generate_itinerary'] ?? 0) + 1;
        session()->put('pending_reward_activities', $pendingRewards);

        return back()
            ->withInput()
            ->with('success', __('messages.route_calculated', ['title' => $routeResult['title']]))
            ->with('routeResult', $routeResult)
            ->with('routeOptions', $routeOptions);
    }

    /** Find the exact top routes from the first place using k-best Held-Karp. */
    private function findOptimalRoutes(
        array $places,
        array $metrics,
        string $preference,
        int $optionCount,
        string $travelMode = 'TRANSIT'
    ): array
    {
        $placeCount = count($places);
        $metricName = match ($preference) {
            'fastest' => 'durations',
            'lowest_cost' => 'fares',
            default => 'distances',
        };
        $optimizationValues = $metrics[$metricName];

        if ($preference === 'lowest_cost') {
            for ($origin = 0; $origin < $placeCount; $origin++) {
                for ($destination = 0; $destination < $placeCount; $destination++) {
                    if ($origin !== $destination && $optimizationValues[$origin][$destination] === null) {
                        throw new UnexpectedValueException(
                            __('route.fare_incomplete')
                        );
                    }
                }
            }
        }

        $states = ['1,0' => [['cost' => 0.0, 'path' => [0]]]];
        $allVisitedMask = (1 << $placeCount) - 1;

        for ($mask = 1; $mask <= $allVisitedMask; $mask++) {
            for ($last = 0; $last < $placeCount; $last++) {
                $key = $mask . ',' . $last;
                if (!isset($states[$key])) {
                    continue;
                }

                foreach ($states[$key] as $candidate) {
                    for ($next = 1; $next < $placeCount; $next++) {
                        if (($mask & (1 << $next)) !== 0) {
                            continue;
                        }

                        $nextMask = $mask | (1 << $next);
                        $nextKey = $nextMask . ',' . $next;
                        $states[$nextKey][] = [
                            'cost' => $candidate['cost'] + $optimizationValues[$last][$next],
                            'path' => [...$candidate['path'], $next],
                        ];

                        usort(
                            $states[$nextKey],
                            fn (array $left, array $right): int => $left['cost'] <=> $right['cost']
                        );
                        $states[$nextKey] = array_slice($states[$nextKey], 0, $optionCount);
                    }
                }
            }
        }

        $completedRoutes = [];
        for ($last = 1; $last < $placeCount; $last++) {
            $completedRoutes = [
                ...$completedRoutes,
                ...($states[$allVisitedMask . ',' . $last] ?? []),
            ];
        }

        usort(
            $completedRoutes,
            fn (array $left, array $right): int => $left['cost'] <=> $right['cost']
        );

        return array_map(
            fn (array $candidate, int $index): array => $this->buildRouteResult(
                $places,
                $metrics,
                $preference,
                $candidate['path'],
                $index,
                $travelMode
            ),
            array_slice($completedRoutes, 0, $optionCount),
            range(0, min($optionCount, count($completedRoutes)) - 1)
        );
    }

    private function buildRouteResult(
        array $places,
        array $metrics,
        string $preference,
        array $path,
        int $optionIndex,
        string $travelMode
    ): array {
        $stops = [];
        foreach ($path as $position => $placeIndex) {
            $stops[] = [
                'name' => $places[$placeIndex]['name'],
                'route_key' => $places[$placeIndex]['route_key'],
                'place_id' => $places[$placeIndex]['place_id'] ?? null,
                'latitude' => $places[$placeIndex]['latitude'] ?? null,
                'longitude' => $places[$placeIndex]['longitude'] ?? null,
                'distance_from_previous' => $position === 0
                    ? 0.0
                    : round($metrics['distances'][$path[$position - 1]][$placeIndex] / 1000, 2),
            ];
        }

        $totals = ['distance' => 0.0, 'duration' => 0.0, 'fare' => 0.0];
        $hasCompleteFare = true;

        for ($position = 1; $position < count($path); $position++) {
            $from = $path[$position - 1];
            $to = $path[$position];
            $totals['distance'] += $metrics['distances'][$from][$to];
            $totals['duration'] += $metrics['durations'][$from][$to];

            if ($metrics['fares'][$from][$to] === null) {
                $hasCompleteFare = false;
            } else {
                $totals['fare'] += $metrics['fares'][$from][$to];
            }
        }

        $labels = [
            'fastest' => [
                'title' => __('route.fastest_title'),
                'description' => __('route.fastest_result'),
            ],
            'shortest' => [
                'title' => __('route.shortest_title'),
                'description' => __('route.shortest_result'),
            ],
            'lowest_cost' => [
                'title' => __('route.lowest_title'),
                'description' => __('route.lowest_result'),
            ],
        ];

        return [
            'preference' => $preference,
            'option_index' => $optionIndex,
            'option_label' => $optionIndex === 0
                ? __('route.recommended')
                : __('route.alternative', ['number' => $optionIndex]),
            'title' => $labels[$preference]['title'],
            'description' => $labels[$preference]['description'],
            'stops' => $stops,
            'total_distance' => round($totals['distance'] / 1000, 2),
            'total_duration_minutes' => (int) ceil($totals['duration'] / 60),
            'total_duration_display' => $this->formatDuration($totals['duration']),
            'total_fare' => $hasCompleteFare ? round($totals['fare'], 2) : null,
            'fare_currency' => $metrics['fare_currency'],
            'travel_mode' => $travelMode,
        ];
    }

    /** Fetch distance, duration, and available fare for every pair of places. */
    private function getGoogleTransitMetrics(
        array $places,
        string $travelMode = 'TRANSIT',
        bool $allowPartial = false
    ): array
    {
        $waypoints = array_map(fn (array $place): array => [
            'waypoint' => $this->routeWaypoint($place),
        ], $places);

        $elements = Http::acceptJson()
            ->withHeaders([
                'X-Goog-Api-Key' => config('services.google_maps.routes_api_key'),
                'X-Goog-FieldMask' => 'originIndex,destinationIndex,status,condition,distanceMeters,duration,travelAdvisory.transitFare',
            ])
            ->timeout(20)
            ->post('https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix', [
                'origins' => $waypoints,
                'destinations' => $waypoints,
                'travelMode' => $travelMode,
            ])
            ->throw()
            ->json();

        $placeCount = count($places);
        $distances = array_fill(0, $placeCount, array_fill(0, $placeCount, null));
        $durations = array_fill(0, $placeCount, array_fill(0, $placeCount, null));
        $fares = array_fill(0, $placeCount, array_fill(0, $placeCount, null));
        $fareCurrency = null;

        foreach ($elements as $element) {
            $origin = $element['originIndex'] ?? null;
            $destination = $element['destinationIndex'] ?? null;

            if ($origin === null || $destination === null) {
                continue;
            }

            if ($origin === $destination) {
                $distances[$origin][$destination] = 0;
                $durations[$origin][$destination] = 0;
                $fares[$origin][$destination] = 0;
                continue;
            }

            if (($element['condition'] ?? null) === 'ROUTE_EXISTS'
                && isset($element['distanceMeters'], $element['duration'])) {
                $distances[$origin][$destination] = (float) $element['distanceMeters'];
                $durations[$origin][$destination] = $this->durationToSeconds($element['duration']);

                $fare = $element['travelAdvisory']['transitFare'] ?? null;
                if ($fare !== null) {
                    $fareValue = $this->moneyToFloat($fare);

                    if ($fareValue > 0) {
                        $fares[$origin][$destination] = $fareValue;
                        $fareCurrency ??= $fare['currencyCode'] ?? null;
                    }
                }
            }
        }

        for ($origin = 0; $origin < $placeCount; $origin++) {
            for ($destination = 0; $destination < $placeCount; $destination++) {
                if (!$allowPartial && ($distances[$origin][$destination] === null
                    || $durations[$origin][$destination] === null)) {
                    throw new \RuntimeException('Google Maps did not return every required route.');
                }
            }
        }

        return [
            'distances' => $distances,
            'durations' => $durations,
            'fares' => $fares,
            'fare_currency' => $fareCurrency,
        ];
    }

    private function connectedRouteSubset(array $places, array $metrics): array
    {
        $activeIndexes = array_keys($places);
        $omittedPlaces = [];

        while (count($activeIndexes) >= 2) {
            $missingScores = array_fill_keys($activeIndexes, 0);

            foreach ($activeIndexes as $origin) {
                foreach ($activeIndexes as $destination) {
                    if ($origin === $destination) {
                        continue;
                    }

                    if ($metrics['distances'][$origin][$destination] === null
                        || $metrics['durations'][$origin][$destination] === null) {
                        $missingScores[$origin]++;
                        $missingScores[$destination]++;
                    }
                }
            }

            $highestMissingScore = max($missingScores);
            if ($highestMissingScore === 0) {
                break;
            }

            $removeIndex = array_search($highestMissingScore, $missingScores, true);
            $omittedPlaces[] = $places[$removeIndex]['name'];
            $activeIndexes = array_values(array_filter(
                $activeIndexes,
                fn (int $index): bool => $index !== $removeIndex
            ));
        }

        $filteredPlaces = array_map(
            fn (int $index): array => $places[$index],
            $activeIndexes
        );
        $filteredMetrics = [];

        foreach (['distances', 'durations', 'fares'] as $metricName) {
            $filteredMetrics[$metricName] = array_map(
                fn (int $origin): array => array_map(
                    fn (int $destination) => $metrics[$metricName][$origin][$destination],
                    $activeIndexes
                ),
                $activeIndexes
            );
        }
        $filteredMetrics['fare_currency'] = $metrics['fare_currency'];

        return [$filteredPlaces, $filteredMetrics, $omittedPlaces];
    }

    /** Fetch a drawable transit route for each consecutive stop. */
    private function getTransitLegs(
        array $stops,
        string $travelMode = 'TRANSIT'
    ): array
    {
        $legs = [];
        $departureTime = CarbonImmutable::now('UTC')->addMinutes(2);

        for ($index = 0; $index < count($stops) - 1; $index++) {
            $from = $stops[$index];
            $to = $stops[$index + 1];

            $routeRequest = [
                'origin' => $this->routeWaypoint($from),
                'destination' => $this->routeWaypoint($to),
                'travelMode' => $travelMode,
                'computeAlternativeRoutes' => false,
                'languageCode' => app()->getLocale() === 'zh' ? 'zh-CN' : app()->getLocale(),
                'units' => 'METRIC',
            ];
            if ($travelMode === 'TRANSIT') {
                $routeRequest['departureTime'] = $departureTime->toRfc3339String();
            }

            $response = Http::acceptJson()
                ->withHeaders([
                    'X-Goog-Api-Key' => config('services.google_maps.routes_api_key'),
                    'X-Goog-FieldMask' => implode(',', [
                        'routes.distanceMeters',
                        'routes.duration',
                        'routes.travelAdvisory.transitFare',
                        'routes.legs.steps.distanceMeters',
                        'routes.legs.steps.staticDuration',
                        'routes.legs.steps.travelMode',
                        'routes.legs.steps.polyline.encodedPolyline',
                        'routes.legs.steps.transitDetails.stopDetails',
                        'routes.legs.steps.transitDetails.transitLine',
                        'routes.legs.steps.transitDetails.headsign',
                        'routes.legs.steps.transitDetails.stopCount',
                    ]),
                ])
                ->timeout(20)
                ->post('https://routes.googleapis.com/directions/v2:computeRoutes', $routeRequest)
                ->throw()
                ->json('routes.0');

            $encodedPolylines = [];
            $tripSteps = [];
            $cursor = $departureTime;
            $currentLocation = $from['name'];

            $apiSteps = [];
            foreach ($response['legs'] ?? [] as $routeLeg) {
                $apiSteps = [...$apiSteps, ...($routeLeg['steps'] ?? [])];
            }

            foreach ($apiSteps as $stepIndex => $step) {
                $encodedPolyline = $step['polyline']['encodedPolyline'] ?? null;

                if ($encodedPolyline) {
                    $encodedPolylines[] = $encodedPolyline;
                }

                $stepDuration = $this->durationToSeconds($step['staticDuration'] ?? '0s');
                $mode = $step['travelMode'] ?? 'WALK';
                $transitDetails = $step['transitDetails'] ?? [];
                $stopDetails = $transitDetails['stopDetails'] ?? [];

                if ($mode === 'TRANSIT') {
                    $stepDeparture = isset($stopDetails['departureTime'])
                        ? CarbonImmutable::parse($stopDetails['departureTime'])
                        : $cursor;
                    $stepArrival = isset($stopDetails['arrivalTime'])
                        ? CarbonImmutable::parse($stopDetails['arrivalTime'])
                        : $stepDeparture->addSeconds($stepDuration);

                    if ($stepDeparture->greaterThan($cursor)) {
                        $tripSteps[] = $this->makeTimelineStep(
                            'WAIT',
                            __('route.wait'),
                            $currentLocation,
                            $currentLocation,
                            $cursor,
                            $stepDeparture,
                            0
                        );
                    }

                    $departureStop = $stopDetails['departureStop']['name'] ?? $currentLocation;
                    $arrivalStop = $stopDetails['arrivalStop']['name'] ?? $to['name'];
                    $line = $transitDetails['transitLine'] ?? [];
                    $transport = $this->transitLabel($line);

                    $tripSteps[] = array_merge(
                        $this->makeTimelineStep(
                            $transport,
                            $line['nameShort'] ?? $line['name'] ?? $transport,
                            $departureStop,
                            $arrivalStop,
                            $stepDeparture,
                            $stepArrival,
                            (int) ($step['distanceMeters'] ?? 0)
                        ),
                        [
                            'headsign' => $transitDetails['headsign'] ?? null,
                            'stop_count' => $transitDetails['stopCount'] ?? null,
                        ]
                    );
                    $cursor = $stepArrival;
                    $currentLocation = $arrivalStop;
                    continue;
                }

                $nextTransitStop = $to['name'];
                for ($lookAhead = $stepIndex + 1; $lookAhead < count($apiSteps); $lookAhead++) {
                    $candidateStop = $apiSteps[$lookAhead]['transitDetails']['stopDetails']['departureStop']['name'] ?? null;
                    if ($candidateStop) {
                        $nextTransitStop = $candidateStop;
                        break;
                    }
                }

                $stepArrival = $cursor->addSeconds($stepDuration);
                $stepLabel = match ($mode) {
                    'DRIVE' => __('route.drive'),
                    'BICYCLE' => __('route.cycle'),
                    default => __('route.walk'),
                };
                $tripSteps[] = $this->makeTimelineStep(
                    $mode,
                    $stepLabel,
                    $currentLocation,
                    $nextTransitStop,
                    $cursor,
                    $stepArrival,
                    (int) ($step['distanceMeters'] ?? 0)
                );
                $cursor = $stepArrival;
                $currentLocation = $nextTransitStop;
            }

            if ($encodedPolylines === []) {
                throw new \RuntimeException('Google Maps did not return a transit route leg.');
            }

            $durationSeconds = $this->durationToSeconds($response['duration'] ?? '0s');
            $arrivalTime = $departureTime->addSeconds($durationSeconds);
            $fare = $response['travelAdvisory']['transitFare'] ?? null;
            $fareValue = $fare !== null ? $this->moneyToFloat($fare) : null;
            $transportParts = [];

            foreach ($tripSteps as $tripStep) {
                if ($tripStep['mode'] === 'WAIT') {
                    continue;
                }

                if ($tripStep['mode'] === 'WALK') {
                    $part = __('route.walk_to', ['place' => $tripStep['to']]);
                } else {
                    $part = $tripStep['mode'] . ' ' . $tripStep['label']
                        . ' (' . $tripStep['from'] . ' to ' . $tripStep['to'] . ')';
                }

                if ($transportParts === [] || end($transportParts) !== $part) {
                    $transportParts[] = $part;
                }
            }

            $legs[] = [
                'from' => $from['name'],
                'to' => $to['name'],
                'distance' => round(($response['distanceMeters'] ?? 0) / 1000, 2),
                'duration_minutes' => (int) ceil($durationSeconds / 60),
                'duration_seconds' => $durationSeconds,
                'duration_display' => $this->formatDuration($durationSeconds),
                'departure_time' => $this->formatTime($departureTime),
                'arrival_time' => $this->formatTime($arrivalTime),
                'steps' => $tripSteps,
                'transport_summary' => implode(' → ', $transportParts),
                'fare' => $fareValue !== null && $fareValue > 0
                    ? round($fareValue, 2)
                    : null,
                'fare_currency' => $fareValue !== null && $fareValue > 0
                    ? ($fare['currencyCode'] ?? 'MYR')
                    : null,
                'encoded_polylines' => $encodedPolylines,
            ];

            $departureTime = $arrivalTime;
        }

        return $legs;
    }

    private function routeWaypoint(array $place): array
    {
        if (!empty($place['place_id'])) {
            return ['placeId' => $place['place_id']];
        }

        return [
            'location' => [
                'latLng' => [
                    'latitude' => $place['latitude'],
                    'longitude' => $place['longitude'],
                ],
            ],
        ];
    }

    private function placesFromDestinationKeys(
        array $destinationKeys,
        bool $savedSource,
        $collectionId = null
    ): array
    {
        if (count($destinationKeys) !== count(array_unique($destinationKeys))) {
            throw new UnexpectedValueException(__('route.duplicate'));
        }

        if (!$savedSource) {
            $places = [];
            foreach ($destinationKeys as $destinationKey) {
                if (!preg_match('/^catalog:(\d+)$/', $destinationKey, $matches)) {
                    throw new UnexpectedValueException(__('route.invalid'));
                }

                $placeIndex = (int) $matches[1];
                if (!isset(self::PLACES[$placeIndex])) {
                    throw new UnexpectedValueException(__('route.invalid'));
                }

                $places[] = [
                    ...self::PLACES[$placeIndex],
                    'route_key' => $destinationKey,
                ];
            }

            return $places;
        }

        $collection = $this->collectionForCurrentUser($collectionId);
        $savedPlaces = ($collection
            ? $this->savedPlacesForCollection($collection)
            : $this->savedPlacesForCurrentUser())
            ->keyBy(fn ($wishlist): string => 'wishlist:' . $wishlist->wishlist_id);
        $places = [];

        foreach ($destinationKeys as $destinationKey) {
            if (!preg_match('/^wishlist:\d+$/', $destinationKey)
                || !$savedPlaces->has($destinationKey)) {
                throw new UnexpectedValueException(__('route.invalid_saved'));
            }

            $wishlist = $savedPlaces->get($destinationKey);
            $places[] = [
                'name' => $wishlist->attraction->attraction_name,
                'place_id' => $wishlist->attraction->place_id,
                'route_key' => $destinationKey,
            ];
        }

        return $places;
    }

    private function savedPlacesForCurrentUser()
    {
        return Wishlist::with('attraction')
            ->where('user_id', Auth::id())
            ->whereHas('attraction', fn ($query) => $query->whereNotNull('place_id'))
            ->get();
    }

    private function durationToSeconds(string $duration): float
    {
        return (float) rtrim($duration, 's');
    }

    private function moneyToFloat(array $money): float
    {
        return (float) ($money['units'] ?? 0)
            + ((int) ($money['nanos'] ?? 0) / 1000000000);
    }

    private function makeTimelineStep(
        string $mode,
        string $label,
        string $from,
        string $to,
        CarbonImmutable $departure,
        CarbonImmutable $arrival,
        int $distanceMeters
    ): array {
        return [
            'mode' => $mode,
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'departure_time' => $this->formatTime($departure),
            'arrival_time' => $this->formatTime($arrival),
            'duration' => $this->formatDuration($arrival->diffInSeconds($departure)),
            'distance' => $distanceMeters >= 1000
                ? number_format($distanceMeters / 1000, 1) . ' km'
                : $distanceMeters . ' m',
        ];
    }

    private function collectionForCurrentUser($collectionId): ?SavedPlaceCollection
    {
        if (!$collectionId) {
            return null;
        }

        return SavedPlaceCollection::where('collection_id', $collectionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }

    private function savedPlacesForCollection(SavedPlaceCollection $collection)
    {
        return Wishlist::with('attraction')
            ->where('user_id', Auth::id())
            ->whereHas('attraction', fn ($query) => $query->whereNotNull('place_id'))
            ->whereIn('attraction_id', $collection->items()->pluck('attraction_id'))
            ->get();
    }

    private function transitLabel(array $line): string
    {
        $lineDescription = strtolower(
            ($line['nameShort'] ?? '') . ' ' . ($line['name'] ?? '')
        );

        foreach (['MRT', 'LRT', 'Monorail'] as $railType) {
            if (str_contains($lineDescription, strtolower($railType))) {
                return strtoupper($railType === 'Monorail' ? 'MONORAIL' : $railType);
            }
        }

        return match ($line['vehicle']['type'] ?? null) {
            'BUS' => 'BUS',
            'SUBWAY' => 'MRT/LRT',
            'HEAVY_RAIL', 'COMMUTER_TRAIN', 'HIGH_SPEED_TRAIN', 'RAIL' => 'TRAIN',
            'TRAM' => 'TRAM',
            default => 'TRANSIT',
        };
    }

    private function formatTime(CarbonImmutable $time): string
    {
        return $time->setTimezone('Asia/Kuala_Lumpur')->format('g:i A');
    }

    private function formatDuration(float $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0) {
            return $remainingMinutes . ' min';
        }

        return $hours . ' hr' . ($hours > 1 ? 's' : '')
            . ($remainingMinutes > 0 ? ' ' . $remainingMinutes . ' min' : '');
    }
}
