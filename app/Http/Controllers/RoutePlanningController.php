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
        $savedPlacesCount = 0;

        if ($usingSavedPlaces) {
            $collection = $this->collectionForCurrentUser($request->query('collection'));
            $savedPlacesQuery = $this->savedPlacesQuery($collection);
            $savedPlacesCount = (clone $savedPlacesQuery)->count();
            $savedPlaces = (clone $savedPlacesQuery)
                ->orderByDesc('wishlist_id')
                ->limit(8)
                ->get();

            $initialSavedPlaces = (clone $savedPlacesQuery)
                ->orderByDesc('wishlist_id')
                ->limit(20)
                ->get();
            $selectedWishlistIds = collect(old(
                'destination_keys',
                session('routeResult')
                    ? array_column(session('routeResult')['stops'], 'route_key')
                    : []
            ))->map(function ($key) {
                return preg_match('/^wishlist:(\d+)$/', (string) $key, $matches)
                    ? (int) $matches[1]
                    : null;
            })->filter()->values();

            if ($selectedWishlistIds->isNotEmpty()) {
                $initialSavedPlaces = $initialSavedPlaces
                    ->concat((clone $savedPlacesQuery)->whereIn('wishlist_id', $selectedWishlistIds)->get())
                    ->unique('wishlist_id')
                    ->values();
            }
        }
        $availablePlaces = $usingSavedPlaces
            ? $initialSavedPlaces->map(fn ($wishlist): array => [
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
            'savedPlacesCount' => $savedPlacesCount,
            'collection' => $collection,
            'availablePlaces' => $availablePlaces,
            'requiresFlight' => $this->placesRequireFlight(
                $savedPlaces->map(fn ($wishlist): array => [
                    'state_name' => $wishlist->attraction->state?->state_name,
                ])->all()
            ),
        ]);
    }

    public function searchSavedPlaces(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'collection_id' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $collection = $this->collectionForCurrentUser($validated['collection_id'] ?? null);
        $query = $this->savedPlacesQuery($collection);
        $term = trim($validated['q'] ?? '');

        if ($term !== '') {
            $query->whereHas('attraction', function ($attractionQuery) use ($term) {
                $attractionQuery->where(function ($matchQuery) use ($term) {
                    $matchQuery->where('attraction_name', 'like', '%' . $term . '%')
                        ->orWhere('category', 'like', '%' . $term . '%')
                        ->orWhereHas('state', fn ($stateQuery) =>
                            $stateQuery->where('state_name', 'like', '%' . $term . '%'));
                });
            });
        }

        $results = $query->orderByDesc('wishlist_id')->paginate(20);

        return response()->json([
            'data' => $results->getCollection()->map(fn ($wishlist): array => [
                'name' => $wishlist->attraction->attraction_name,
                'route_key' => 'wishlist:' . $wishlist->wishlist_id,
                'category' => $wishlist->attraction->category,
                'state' => $wishlist->attraction->state?->state_name,
            ])->values(),
            'current_page' => $results->currentPage(),
            'has_more' => $results->hasMorePages(),
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
                'between:0,3',
            ],
            'travel_mode' => ['nullable', 'in:TRANSIT,DRIVE,WALK,BICYCLE'],
            'destination_keys' => ['required', 'array', 'min:2', 'max:8'],
            'destination_keys.*' => ['required', 'string'],
            'source' => ['nullable', 'in:saved'],
            'collection_id' => ['nullable', 'integer'],
            'start_time' => ['nullable', 'date_format:H:i'],
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

        $collection = ($validated['source'] ?? null) === 'saved'
            ? $this->collectionForCurrentUser($validated['collection_id'] ?? null)
            : null;
        $planningTimezone = config('app.timezone', 'Asia/Kuala_Lumpur');
        $planningStartDate = $collection?->start_date
            ? CarbonImmutable::parse($collection->start_date, $planningTimezone)->startOfDay()
            : CarbonImmutable::now($planningTimezone)->startOfDay();
        $planningEndDate = $collection?->end_date
            ? CarbonImmutable::parse($collection->end_date, $planningTimezone)->startOfDay()
            : $planningStartDate;
        $planningStartTime = $collection?->start_time
            ? substr((string) $collection->start_time, 0, 5)
            : ($validated['start_time'] ?? CarbonImmutable::now($planningTimezone)->format('H:i'));

        // For a standalone one-day plan, an explicitly selected time that has
        // already passed means the user intends to travel tomorrow. A blank
        // time continues to mean "start now" today.
        if ($collection === null && !empty($validated['start_time'])) {
            [$requestedHour, $requestedMinute] = array_map(
                'intval',
                explode(':', $validated['start_time'])
            );
            $requestedDeparture = $planningStartDate->setTime(
                $requestedHour,
                $requestedMinute
            );

            if ($requestedDeparture->lessThanOrEqualTo(CarbonImmutable::now($planningTimezone))) {
                $planningStartDate = $planningStartDate->addDay();
                $planningEndDate = $planningStartDate;
            }
        }

        try {
            $crossRegion = $this->placesRequireFlight($places);
            $routeOptions = $crossRegion
                ? [$this->buildCrossRegionRoute(
                    $places,
                    $planningStartDate,
                    $planningEndDate,
                    $planningStartTime
                )]
                : $this->buildTransportModeOptions(
                    $places,
                    $validated['optimization_preference']
                );

            if ($routeOptions === []) {
                throw new UnexpectedValueException(__('route.connect_error'));
            }

            $requestedTravelMode = $validated['travel_mode'] ?? null;
            $selectedOptionIndex = $requestedTravelMode
                ? array_search($requestedTravelMode, array_column($routeOptions, 'travel_mode'), true)
                : (int) ($validated['route_option_index'] ?? 0);
            $selectedOptionIndex = $selectedOptionIndex === false ? 0 : $selectedOptionIndex;
            $routeResult = $routeOptions[$selectedOptionIndex] ?? $routeOptions[0];
            $travelMode = $routeResult['travel_mode'];
            $routeResult['omitted_places'] = [];
            $routeResult['fallback_notice'] = null;
            if (!$crossRegion) {
                $routeResult['transit_legs'] = $this->getTransitLegs(
                    $routeResult['stops'],
                    $travelMode,
                    $planningStartDate,
                    $planningEndDate,
                    $planningStartTime
                );
            }
            $routeResult['trip_start_date'] = $planningStartDate->toDateString();
            $routeResult['trip_end_date'] = $planningEndDate->toDateString();
            $routeResult['trip_day_count'] = $planningStartDate->diffInDays($planningEndDate) + 1;
            $routeResult['is_collection_plan'] = $collection !== null;
            $routeResult['trip_start_time'] = $planningStartTime;

            if ($routeResult['transit_legs'] !== []) {
                $routeResult['departure_time'] = $routeResult['transit_legs'][0]['visit_start_time'];
                $lastLegIndex = array_key_last($routeResult['transit_legs']);
                $lastLeg = $routeResult['transit_legs'][$lastLegIndex];
                $finalVisitMinutes = (int) (last($routeResult['stops'])['suggested_visit_minutes'] ?? 0);
                $finalVisitEnd = CarbonImmutable::parse($lastLeg['arrival_at'])->addMinutes($finalVisitMinutes);
                $routeResult['arrival_time'] = $this->formatTime($finalVisitEnd);
                $routeResult['final_visit_end_time'] = $this->formatTime($finalVisitEnd);
                $knownDuration = array_sum(array_column($routeResult['transit_legs'], 'duration_seconds'))
                    + (array_sum(array_column($routeResult['transit_legs'], 'visit_duration_minutes')) * 60)
                    + ($finalVisitMinutes * 60);
                $routeResult['total_duration_display'] = $this->formatDuration($knownDuration);
                if (!empty($routeResult['has_unestimated_transfer'])) {
                    $routeResult['total_duration_display'] = $knownDuration > 0
                        ? $routeResult['total_duration_display'] . ' local + transfer time'
                        : 'Check flight/ferry schedule';
                }
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

    /** Build local route legs and explicit air/sea transfers across Malaysia. */
    private function buildCrossRegionRoute(
        array $places,
        CarbonImmutable $planningStartDate,
        CarbonImmutable $planningEndDate,
        string $planningStartTime
    ): array {
        $legs = [];
        $planningTimezone = config('app.timezone', 'Asia/Kuala_Lumpur');
        $cursor = CarbonImmutable::parse(
            $planningStartDate->toDateString() . ' ' . $planningStartTime,
            $planningTimezone
        );
        $minimumStart = CarbonImmutable::now($planningTimezone)->addMinutes(2);
        if ($cursor->isBefore($minimumStart)) {
            $cursor = $minimumStart;
        }
        $transferBufferSeconds = 4 * 60 * 60;

        for ($index = 0; $index < count($places) - 1; $index++) {
            $from = $places[$index];
            $to = $places[$index + 1];
            $legDate = $cursor->startOfDay();
            $visitMinutes = $this->suggestedVisitMinutes($from);

            if ($this->placesRequireFlight([$from, $to])) {
                $visitStart = $cursor;
                $cursor = $cursor->addMinutes($visitMinutes);
                $displayDate = $legDate->format('D, d M Y');
                $displayTime = $cursor->format('g:i A');
                $transferArrival = $cursor->addSeconds($transferBufferSeconds);
                $legs[] = [
                    'from' => $from['name'],
                    'to' => $to['name'],
                    'navigation_url' => null,
                    'distance' => 0,
                    'duration_minutes' => 240,
                    'duration_seconds' => $transferBufferSeconds,
                    'duration_display' => 'Estimated 4 hrs (not actual travel time)',
                    'departure_time' => $displayTime,
                    'arrival_time' => $transferArrival->format('g:i A'),
                    'arrival_at' => $transferArrival->toIso8601String(),
                    'trip_date' => $legDate->toDateString(),
                    'trip_date_display' => $displayDate,
                    'visit_place' => $from['name'],
                    'visit_start_time' => $visitStart->format('g:i A'),
                    'visit_end_time' => $cursor->format('g:i A'),
                    'visit_duration_minutes' => $visitMinutes,
                    'visit_duration_display' => $this->formatVisitDuration($visitMinutes),
                    'segment_duration_display' => $this->formatDuration(
                        $transferBufferSeconds + ($visitMinutes * 60)
                    ),
                    'steps' => [[
                        'mode' => 'FLIGHT_OR_FERRY',
                        'label' => 'Take a flight or ferry to East/West Malaysia',
                        'from' => $from['name'],
                        'to' => $to['name'],
                        'departure_time' => $displayTime,
                        'arrival_time' => $transferArrival->format('g:i A'),
                        'duration' => 'Estimated 4 hrs — confirm with operator',
                        'distance' => 'Not estimated',
                    ]],
                    'transport_summary' => 'Flight or ferry required',
                    'fare' => null,
                    'fare_currency' => null,
                    'encoded_polylines' => [],
                    'is_cross_region_transfer' => true,
                ];
                $cursor = $transferArrival;
                continue;
            }

            try {
                $localLegs = $this->getTransitLegs(
                    [$from, $to],
                    'TRANSIT',
                    $legDate,
                    $legDate,
                    $cursor->format('H:i'),
                    $cursor
                );
            } catch (Throwable $exception) {
                $localLegs = $this->getTransitLegs(
                    [$from, $to],
                    'DRIVE',
                    $legDate,
                    $legDate,
                    $cursor->format('H:i'),
                    $cursor
                );
            }

            $legs = [...$legs, ...$localLegs];
            $cursor = $cursor
                ->addMinutes($visitMinutes)
                ->addSeconds(array_sum(array_column($localLegs, 'duration_seconds')));
        }

        $stops = array_map(fn (array $place): array => [
            'name' => $place['name'],
            'route_key' => $place['route_key'],
            'place_id' => $place['place_id'] ?? null,
            'latitude' => $place['latitude'] ?? null,
            'longitude' => $place['longitude'] ?? null,
            'suggested_visit_minutes' => $this->suggestedVisitMinutes($place),
            'suggested_visit_display' => $this->formatVisitDuration($this->suggestedVisitMinutes($place)),
            'distance_from_previous' => 0,
        ], $places);

        return [
            'preference' => 'fastest',
            'option_index' => 0,
            'option_label' => 'Mixed transport',
            'title' => 'Cross-region Malaysia itinerary',
            'description' => 'Local routes are combined with a flight or ferry transfer while preserving your stop order.',
            'stops' => $stops,
            'total_distance' => array_sum(array_column($legs, 'distance')),
            'total_duration_minutes' => (int) ceil(array_sum(array_column($legs, 'duration_seconds')) / 60),
            'total_duration_display' => '',
            'total_fare' => null,
            'fare_currency' => null,
            'travel_mode' => 'MIXED',
            'transit_legs' => $legs,
            'has_unestimated_transfer' => false,
        ];
    }

    /** Build travel-mode alternatives without changing the user's stop order. */
    private function buildTransportModeOptions(
        array $places,
        string $preference
    ): array
    {
        $path = array_keys($places);
        $options = [];

        foreach (['TRANSIT', 'DRIVE', 'WALK', 'BICYCLE'] as $travelMode) {
            try {
                $metrics = $this->getGoogleTransitMetrics($places, $travelMode);
                $options[] = $this->buildRouteResult(
                    $places,
                    $metrics,
                    $preference,
                    $path,
                    0,
                    $travelMode
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $sortField = match ($preference) {
            'shortest' => 'total_distance',
            'lowest_cost' => 'total_fare',
            default => 'total_duration_minutes',
        };
        usort($options, function (array $left, array $right) use ($sortField): int {
            $leftValue = $left[$sortField] ?? PHP_FLOAT_MAX;
            $rightValue = $right[$sortField] ?? PHP_FLOAT_MAX;
            return $leftValue <=> $rightValue;
        });

        foreach ($options as $index => &$option) {
            $option['option_index'] = $index;
            $option['option_label'] = $this->travelModeLabel($option['travel_mode']);
        }
        unset($option);

        return $options;
    }

    private function travelModeLabel(string $travelMode): string
    {
        return match ($travelMode) {
            'DRIVE' => __('route.drive'),
            'WALK' => __('route.walk'),
            'BICYCLE' => __('route.cycle'),
            default => __('route.public_transport'),
        };
    }

    private function placesRequireFlight(array $places): bool
    {
        $hasEastMalaysia = false;
        $hasPeninsularMalaysia = false;

        foreach ($places as $place) {
            $stateName = $place['state_name'] ?? null;
            if (!$stateName) {
                continue;
            }

            if (in_array($stateName, ['Sabah', 'Sarawak', 'Labuan'], true)) {
                $hasEastMalaysia = true;
            } else {
                $hasPeninsularMalaysia = true;
            }
        }

        return $hasEastMalaysia && $hasPeninsularMalaysia;
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
                'suggested_visit_minutes' => $this->suggestedVisitMinutes($places[$placeIndex]),
                'suggested_visit_display' => $this->formatVisitDuration($this->suggestedVisitMinutes($places[$placeIndex])),
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

        // Total itinerary duration includes the suggested time spent at every
        // attraction as well as travel between stops.
        $totals['duration'] += array_sum(array_column($stops, 'suggested_visit_minutes')) * 60;

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
        string $travelMode = 'TRANSIT',
        ?CarbonImmutable $planningStartDate = null,
        ?CarbonImmutable $planningEndDate = null,
        ?string $planningStartTime = null,
        ?CarbonImmutable $initialDepartureTime = null
    ): array
    {
        $legs = [];
        $planningTimezone = config('app.timezone', 'Asia/Kuala_Lumpur');
        $planningStartDate ??= CarbonImmutable::now($planningTimezone)->startOfDay();
        $planningEndDate ??= $planningStartDate;
        $planningStartTime ??= CarbonImmutable::now($planningTimezone)->format('H:i');
        [$startHour, $startMinute] = array_map('intval', explode(':', $planningStartTime));
        $dayCount = max(1, $planningStartDate->diffInDays($planningEndDate) + 1);
        $legCount = max(1, count($stops) - 1);
        $activeDayOffset = $initialDepartureTime ? 0 : null;
        $departureTime = $initialDepartureTime
            ? $initialDepartureTime->setTimezone($planningTimezone)
            : CarbonImmutable::now($planningTimezone)->addMinutes(2);

        for ($index = 0; $index < count($stops) - 1; $index++) {
            $from = $stops[$index];
            $to = $stops[$index + 1];
            $dayOffset = min($dayCount - 1, (int) floor($index * $dayCount / $legCount));

            if ($activeDayOffset !== $dayOffset) {
                $activeDayOffset = $dayOffset;
                $scheduledStart = $planningStartDate->addDays($dayOffset)->setTime($startHour, $startMinute);
                $minimumStart = CarbonImmutable::now($planningTimezone)->addMinutes(2);
                $departureTime = $scheduledStart->isBefore($minimumStart)
                    ? $minimumStart
                    : $scheduledStart;
            }

            $visitMinutes = (int) ($from['suggested_visit_minutes']
                ?? $this->suggestedVisitMinutes($from));
            $visitStartTime = $departureTime;
            $departureTime = $departureTime->addMinutes($visitMinutes);

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
                'destination_place_id' => $to['place_id'] ?? null,
                'destination_latitude' => $to['latitude'] ?? null,
                'destination_longitude' => $to['longitude'] ?? null,
                'navigation_url' => $this->googleMapsNavigationUrl($to, $travelMode),
                'distance' => round(($response['distanceMeters'] ?? 0) / 1000, 2),
                'duration_minutes' => (int) ceil($durationSeconds / 60),
                'duration_seconds' => $durationSeconds,
                'duration_display' => $this->formatDuration($durationSeconds),
                'departure_time' => $this->formatTime($departureTime),
                'arrival_time' => $this->formatTime($arrivalTime),
                'arrival_at' => $arrivalTime->toIso8601String(),
                'trip_date' => $visitStartTime->toDateString(),
                'trip_date_display' => $visitStartTime->format('D, d M Y'),
                'visit_place' => $from['name'],
                'visit_start_time' => $this->formatTime($visitStartTime),
                'visit_end_time' => $this->formatTime($departureTime),
                'visit_duration_minutes' => $visitMinutes,
                'visit_duration_display' => $this->formatVisitDuration($visitMinutes),
                'segment_duration_display' => $this->formatDuration(
                    $durationSeconds + ($visitMinutes * 60)
                ),
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

    private function googleMapsNavigationUrl(array $destination, string $travelMode): string
    {
        $destinationValue = isset($destination['latitude'], $destination['longitude'])
            ? $destination['latitude'] . ',' . $destination['longitude']
            : $destination['name'];

        $parameters = [
            'api' => 1,
            'destination' => $destinationValue,
            'travelmode' => match ($travelMode) {
                'DRIVE' => 'driving',
                'WALK' => 'walking',
                'BICYCLE' => 'bicycling',
                default => 'transit',
            },
            'dir_action' => 'navigate',
        ];

        if (!empty($destination['place_id'])) {
            $parameters['destination_place_id'] = $destination['place_id'];
        }

        return 'https://www.google.com/maps/dir/?' . http_build_query($parameters);
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
        $wishlistIds = collect($destinationKeys)->map(function ($destinationKey) {
            return preg_match('/^wishlist:(\d+)$/', $destinationKey, $matches)
                ? (int) $matches[1]
                : null;
        })->filter()->values();
        $savedPlaces = $this->savedPlacesQuery($collection)
            ->whereIn('wishlist_id', $wishlistIds)
            ->get()
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
                'category' => $wishlist->attraction->category,
                'state_name' => $wishlist->attraction->state?->state_name,
                'is_east_malaysia' => in_array(
                    $wishlist->attraction->state?->state_name,
                    ['Sabah', 'Sarawak', 'Labuan'],
                    true
                ),
            ];
        }

        return $places;
    }

    private function savedPlacesForCurrentUser()
    {
        return $this->savedPlacesQuery()->get();
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
        return $this->savedPlacesQuery($collection)->get();
    }

    private function savedPlacesQuery(?SavedPlaceCollection $collection = null)
    {
        $query = Wishlist::with('attraction.state')
            ->where('user_id', Auth::id())
            ->whereHas('attraction', fn ($query) => $query->whereNotNull('place_id'));

        if ($collection) {
            $query->whereIn(
                'attraction_id',
                $collection->items()->select('attraction_id')
            );
        }

        return $query;
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

    private function suggestedVisitMinutes(array $place): int
    {
        $text = strtolower(($place['category'] ?? '') . ' ' . ($place['name'] ?? ''));

        return match (true) {
            str_contains($text, 'theme park'),
            str_contains($text, 'adventure'),
            str_contains($text, 'water park') => 180,
            str_contains($text, 'nature'),
            str_contains($text, 'beach'),
            str_contains($text, 'island'),
            str_contains($text, 'hiking') => 150,
            str_contains($text, 'museum'),
            str_contains($text, 'heritage'),
            str_contains($text, 'culture'),
            str_contains($text, 'shopping') => 120,
            str_contains($text, 'food'),
            str_contains($text, 'cafe'),
            str_contains($text, 'restaurant') => 90,
            default => 120,
        };
    }

    private function formatVisitDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return ($hours > 0 ? $hours . ' hr' . ($hours > 1 ? 's' : '') : '')
            . ($remainingMinutes > 0 ? ($hours > 0 ? ' ' : '') . $remainingMinutes . ' min' : '');
    }

    private function formatDuration(float $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);
        $days = intdiv($minutes, 1440);
        $minutesAfterDays = $minutes % 1440;
        $hours = intdiv($minutesAfterDays, 60);
        $remainingMinutes = $minutesAfterDays % 60;

        if ($days > 0) {
            return $days . ' day' . ($days > 1 ? 's' : '')
                . ' ' . $hours . ' hr' . ($hours !== 1 ? 's' : '')
                . ' ' . $remainingMinutes . ' min';
        }

        return $hours . ' hr' . ($hours !== 1 ? 's' : '')
            . ' ' . $remainingMinutes . ' min';
    }
}
