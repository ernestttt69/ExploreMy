<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\SavedPlaceCollection;
use App\Services\TransitFareEstimator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Throwable;
use UnexpectedValueException;

class RoutePlanningController extends Controller
{
    public function __construct(private TransitFareEstimator $fareEstimator) {}

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
        if ($request->session()->has('routeResult')) {
            Session::keep(['routeResult', 'routeOptions']);
        }

        $usingSavedPlaces = $request->query('source') === 'saved';
        $previousRoute = session('routeResult');
        if ($previousRoute && (
            (int) ($previousRoute['collection_id'] ?? 0) !== (int) $request->query('collection', 0)
            || ($previousRoute['source'] ?? 'normal') !== ($usingSavedPlaces ? 'saved' : 'normal')
        )) {
            $request->session()->forget(['routeResult', 'routeOptions', '_old_input']);
        }
        $collection = null;
        $savedPlaces = collect();
        $savedPlacesCount = 0;

        if ($usingSavedPlaces) {
            $collection = $this->collectionForCurrentUser($request->query('collection'));
            $savedPlacesQuery = $this->savedPlacesQuery();
            $savedPlacesCount = (clone $savedPlacesQuery)->count();
            $savedPlaces = (clone $savedPlacesQuery)
                ->orderByDesc('wishlist_id')
                ->limit(8)
                ->get();

            $initialSavedPlaces = (clone $savedPlacesQuery)
                ->orderByDesc('wishlist_id')
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
                'state_name' => $wishlist->attraction->state?->state_name,
                'location' => $wishlist->attraction->location,
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

        $selectedDestinationKeys = old(
            'destination_keys',
            session('routeResult')
                ? array_column(session('routeResult')['stops'], 'route_key')
                : array_slice(array_column($availablePlaces, 'route_key'), 0, 2)
        );
        if ($collection) {
            $collectionPlaces = $this->collectionPlaces($collection);
            $collectionPlaceIds = array_column($collectionPlaces, 'place_id');
            $availablePlaces = [...$collectionPlaces, ...array_values(array_filter($availablePlaces,
                fn ($place) => !in_array($place['place_id'], $collectionPlaceIds, true)))];
            $selectedDestinationKeys = old('destination_keys',
                session('routeResult.collection_id') === $collection->getKey()
                    ? array_column(session('routeResult.stops'), 'route_key')
                    : array_column($collectionPlaces, 'route_key'));
            $savedPlacesCount = count($selectedDestinationKeys);
        }
        if (session('routeResult') && !session()->has('errors')
            && session('routeResult.collection_id') === $collection?->getKey()) {
            $selectedDestinationKeys = array_column(session('routeResult.stops'), 'route_key');
        }
        $availablePlaces = array_map(fn ($place) => $place + $this->transferFlags($place), $availablePlaces);
        $selectedPlacesForNotice = array_values(array_filter($availablePlaces,
            fn ($place) => in_array($place['route_key'], $selectedDestinationKeys, true)));

        return view('route-planning.route', [
            'googleMapsBrowserKey' => config('services.google_maps.browser_api_key'),
            'usingSavedPlaces' => $usingSavedPlaces,
            'savedPlaces' => $savedPlaces,
            'savedPlacesCount' => $savedPlacesCount,
            'collection' => $collection,
            'availablePlaces' => $availablePlaces,
            'selectedDestinationKeys' => $selectedDestinationKeys,
            'requiresFlight' => $this->placesRequireFlight($selectedPlacesForNotice),
            'requiresFerry' => $this->usesIslandFerry($selectedPlacesForNotice),
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
                        ->orWhereHas('preferences', fn ($preferenceQuery) =>
                            $preferenceQuery->where('category_name', 'like', '%' . $term . '%'))
                        ->orWhereHas('state', fn ($stateQuery) =>
                            $stateQuery->where('state_name', 'like', '%' . $term . '%'));
                });
            });
        }

        $results = $query->orderByDesc('wishlist_id')->get();

        return response()->json([
            'data' => $results->map(fn ($wishlist): array => [
                'name' => $wishlist->attraction->attraction_name,
                'route_key' => 'wishlist:' . $wishlist->wishlist_id,
                'operating_hours' => $wishlist->attraction->operating_hours,
                'category' => $wishlist->attraction->category,
                'state' => $wishlist->attraction->state?->state_name,
                ...$this->transferFlags([
                    'name' => $wishlist->attraction->attraction_name,
                    'location' => $wishlist->attraction->location,
                    'state_name' => $wishlist->attraction->state?->state_name,
                ]),
            ])->values(),
            'current_page' => 1,
            'has_more' => false,
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
            'destination_keys' => ['required_without:collection_id', 'array', 'min:2'],
            'destination_keys.*' => ['required', 'string'],
            'source' => ['nullable', 'in:saved', 'required_with:collection_id'],
            'collection_id' => ['nullable', 'integer'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'order_mode' => ['nullable', 'in:auto,manual'],
        ]);

        try {
            $places = $this->placesFromDestinationKeys(
                $validated['destination_keys'] ?? [],
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
        if ($planningStartDate->lessThan(CarbonImmutable::now($planningTimezone)->startOfDay())) {
            return back()->withInput()->withErrors([
                'route' => __('messages.route_departure_past'),
            ]);
        }
        $planningStartTime = $validated['start_time'] ?? ($collection?->start_time
            ? substr((string) $collection->start_time, 0, 5)
            : CarbonImmutable::now($planningTimezone)->format('H:i'));

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

        $planningEndTime = array_key_exists('end_time', $validated)
            ? $validated['end_time']
            : ($collection?->end_time ? substr($collection->end_time, 0, 5) : null);
        if ($planningEndTime && $planningEndTime <= $planningStartTime) {
            return back()->withInput()->withErrors(['end_time' => __('schedule.invalid_window')]);
        }

        try {
            $manualOrder = ($validated['order_mode'] ?? 'auto') === 'manual';
            $crossRegion = $this->placesRequireFlight($places);
            $mixedSuggestion = null;
            if ($crossRegion) {
                $metrics = $this->getGoogleTransitMetrics($places, 'TRANSIT', true);
                $path = $manualOrder ? array_keys($places) : app(\App\Services\StopOrderOptimizer::class)->optimize($metrics, $validated['optimization_preference']);
                if (collect($places)->contains(fn ($place) => !empty($place['operating_hours']))) {
                    $timedPlaces = array_map(fn ($place) => $place + [
                        'suggested_visit_minutes' => $this->suggestedVisitMinutes($place),
                        'suggested_visit_display' => $this->formatVisitDuration($this->suggestedVisitMinutes($place)),
                    ], $places);
                    $openingPlan = app(\App\Services\OpeningHoursOrderPlanner::class)->plan(
                        $timedPlaces, $metrics, $path, $validated['optimization_preference'],
                        $planningStartDate->setTimeFromTimeString($planningStartTime), $planningStartTime,
                        $planningEndTime, $collection ? $planningEndDate : null
                    );
                    if ($openingPlan['best']) {
                        if (!$manualOrder) $path = $openingPlan['best']['path'];
                        elseif ($openingPlan['best']['path'] !== $path) {
                            $mixedSuggestion = array_map(fn ($index) => $places[$index]['name'], $openingPlan['best']['path']);
                            if (!$openingPlan['original']) throw new UnexpectedValueException(__('schedule.order_unavailable', ['sequence' => implode(' → ', $mixedSuggestion)]));
                        }
                    }
                }
                $places = array_map(fn ($index) => $places[$index], $path);
            }
            $routeOptions = $crossRegion
                ? [$this->buildCrossRegionRoute(
                    $places,
                    $planningStartDate,
                    $planningEndDate,
                    $planningStartTime
                )]
                : $this->buildTransportModeOptions(
                    $places,
                    $validated['optimization_preference'],
                    $manualOrder,
                    $planningStartDate->setTimeFromTimeString($planningStartTime),
                    $planningStartTime,
                    $planningEndTime,
                    $collection ? $planningEndDate : null
                );

            if ($routeOptions === [] && $this->usesIslandFerry($places)) {
                $routeOptions = [$this->buildCrossRegionRoute(
                    $places,
                    $planningStartDate,
                    $planningEndDate,
                    $planningStartTime,
                    true
                )];
            }

            if ($routeOptions === []) {
                throw new UnexpectedValueException(__('route.connect_error'));
            }

            $requestedTravelMode = $validated['travel_mode'] ?? null;
            $selectedOptionIndex = $requestedTravelMode
                ? array_search($requestedTravelMode, array_column($routeOptions, 'travel_mode'), true)
                : (int) ($validated['route_option_index'] ?? 0);
            $selectedOptionIndex = $selectedOptionIndex === false ? 0 : $selectedOptionIndex;
            $routeResult = $routeOptions[$selectedOptionIndex] ?? $routeOptions[0];
            if ($mixedSuggestion) $routeResult['opening_suggested_order'] = $mixedSuggestion;
            $travelMode = $routeResult['travel_mode'];
            $routeResult['omitted_places'] = [];
            $routeResult['fallback_notice'] = null;
            $routeResult['transit_legs'] ??= [];
            $templates = $routeResult['transit_legs'];
            $scheduled = app(\App\Services\DailyItineraryScheduler::class)->schedule(
                $routeResult['stops'],
                $planningStartDate->setTimeFromTimeString($planningStartTime),
                $planningStartTime,
                $planningEndTime,
                $collection ? $planningEndDate : null,
                function (array $from, array $to, CarbonImmutable $departure, int $index) use ($templates, $travelMode): array {
                    $template = $templates[$index] ?? [];
                    if (!empty($template['is_cross_region_transfer'])) {
                        $arrival = $departure->addSeconds($template['duration_seconds']);
                        $template['departure_time'] = $departure->format('g:i A');
                        $template['steps'][0]['departure_time'] = $departure->format('g:i A');
                        $template['steps'][0]['arrival_time'] = $arrival->format('g:i A');
                        return $template;
                    }
                    $from['suggested_visit_minutes'] = 0;
                    $mode = $travelMode === 'MIXED' ? 'TRANSIT' : $travelMode;
                    try {
                        return $this->getTransitLegs([$from, $to], $mode, $departure->startOfDay(), $departure->startOfDay(), $departure->format('H:i'), $departure)[0];
                    } catch (Throwable $exception) {
                        if ($travelMode !== 'MIXED') {
                            throw $exception;
                        }
                        return $this->getTransitLegs([$from, $to], 'DRIVE', $departure->startOfDay(), $departure->startOfDay(), $departure->format('H:i'), $departure)[0];
                    }
                }
            );
            $routeResult['opening_conflicts'] = $scheduled['opening_conflicts'];
            $routeResult['stops'] = $scheduled['stops'];
            $routeResult['transit_legs'] = $scheduled['transit_legs'];
            foreach ($routeResult['transit_legs'] as $index => &$leg) {
                $leg['segment_duration_display'] = $this->formatDuration($leg['duration_seconds'] + $leg['visit_duration_minutes'] * 60);
                if (isset($leg['origin_latitude'], $leg['origin_longitude'])) {
                    $routeResult['stops'][$index]['latitude'] = $leg['origin_latitude'];
                    $routeResult['stops'][$index]['longitude'] = $leg['origin_longitude'];
                }
                if (isset($leg['destination_latitude'], $leg['destination_longitude'])) {
                    $routeResult['stops'][$index + 1]['latitude'] = $leg['destination_latitude'];
                    $routeResult['stops'][$index + 1]['longitude'] = $leg['destination_longitude'];
                }
            }
            unset($leg);
            $fares = collect($routeResult['transit_legs'])->filter(fn ($leg) => is_numeric($leg['fare'] ?? null));
            if ($fares->isNotEmpty() && $fares->count() === count($routeResult['transit_legs'])) {
                $routeResult['total_fare'] = round($fares->sum('fare'), 2);
                $routeResult['fare_currency'] = $fares->pluck('fare_currency')->filter()->first() ?? 'MYR';
                $routeResult['fare_is_estimated'] = $fares->contains(fn ($leg) => !empty($leg['fare_is_estimated']));
                $routeOptions[$selectedOptionIndex]['total_fare'] = $routeResult['total_fare'];
                $routeOptions[$selectedOptionIndex]['fare_currency'] = $routeResult['fare_currency'];
                $routeOptions[$selectedOptionIndex]['fare_is_estimated'] = $routeResult['fare_is_estimated'];
            }
            if ($collection === null) {
                $planningEndDate = $scheduled['end']->startOfDay();
            }
            $routeResult['order_mode'] = $manualOrder ? 'manual' : 'auto';
            $routeResult['collection_id'] = $collection?->getKey();
            $routeResult['source'] = ($validated['source'] ?? null) === 'saved' ? 'saved' : 'normal';
            if ($manualOrder) {
                $routeResult['description'] = __('schedule.manual_description');
            }
            $routeResult['trip_end_time'] = $planningEndTime;
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
                $finalVisitEnd = isset(last($routeResult['stops'])['visit_end_at'])
                    ? CarbonImmutable::parse(last($routeResult['stops'])['visit_end_at'])
                    : CarbonImmutable::parse($lastLeg['arrival_at'])->addMinutes($finalVisitMinutes);
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
            $message = $exception->getMessage();
            if (!empty($routeResult['opening_suggested_order'])) {
                $message .= ' '.__('schedule.suggest_open_order', ['sequence' => implode(' → ', $routeResult['opening_suggested_order'])]);
            }
            return back()
                ->withInput()
                ->withErrors(['route' => $message]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'route' => __('messages.route_calculation_failed'),
                ]);
        }

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
        string $planningStartTime,
        bool $forceFerryTransfer = false
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

            if ($forceFerryTransfer || $this->placesRequireFlight([$from, $to])) {
                $visitStart = $cursor;
                $cursor = $cursor->addMinutes($visitMinutes);
                $displayDate = $legDate->locale(app()->getLocale())->translatedFormat('D, d M Y');
                $displayTime = $cursor->format('g:i A');
                $transferArrival = $cursor->addSeconds($transferBufferSeconds);
                $legs[] = [
                    'from' => $from['name'],
                    'to' => $to['name'],
                    'navigation_url' => null,
                    'distance' => 0,
                    'duration_minutes' => 240,
                    'duration_seconds' => $transferBufferSeconds,
                    'duration_display' => __('route_form.estimated_duration', ['duration' => $this->formatDuration($transferBufferSeconds)]),
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
                        'mode' => $forceFerryTransfer ? 'FERRY' : 'FLIGHT_OR_FERRY',
                        'label' => $forceFerryTransfer
                            ? __('route_form.ferry_step')
                            : __('route_form.transfer_step'),
                        'from' => $from['name'],
                        'to' => $to['name'],
                        'departure_time' => $displayTime,
                        'arrival_time' => $transferArrival->format('g:i A'),
                        'duration' => __('route_form.confirm_duration', ['duration' => $this->formatDuration($transferBufferSeconds)]),
                        'distance' => __('route_form.not_estimated'),
                    ]],
                    'transport_summary' => $forceFerryTransfer
                        ? __('route_form.ferry_required')
                        : __('route_form.flight_required'),
                    'fare' => null,
                    'fare_currency' => null,
                    'encoded_polylines' => [],
                    'is_cross_region_transfer' => true,
                    'transfer_type' => $forceFerryTransfer ? 'FERRY' : 'FLIGHT_OR_FERRY',
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
            'operating_hours' => $place['operating_hours'] ?? null,
            'suggested_visit_minutes' => $this->suggestedVisitMinutes($place),
            'suggested_visit_display' => $this->formatVisitDuration($this->suggestedVisitMinutes($place)),
            'distance_from_previous' => 0,
        ], $places);

        return [
            'preference' => 'fastest',
            'option_index' => 0,
            'option_label' => __('route_form.mixed_transport'),
            'title' => $forceFerryTransfer
                ? __('route_form.ferry_itinerary_title')
                : __('route_form.cross_region_itinerary_title'),
            'description' => $forceFerryTransfer
                ? __('route_form.ferry_itinerary_description')
                : __('route_form.cross_region_itinerary_description'),
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

    /** Optimize each travel mode unless the user has chosen a specific stop order. */
    private function buildTransportModeOptions(
        array $places,
        string $preference,
        bool $manualOrder = false,
        ?CarbonImmutable $planningStart = null,
        string $dailyStart = '09:00',
        ?string $dailyEnd = null,
        ?CarbonImmutable $lastDate = null
    ): array
    {
        $path = array_keys($places);
        $places = array_map(fn ($place) => $place + [
            'suggested_visit_minutes' => $this->suggestedVisitMinutes($place),
            'suggested_visit_display' => $this->formatVisitDuration($this->suggestedVisitMinutes($place)),
        ], $places);
        $options = [];
        $openingError = null;

        foreach (['TRANSIT', 'DRIVE', 'WALK', 'BICYCLE'] as $travelMode) {
            try {
                $metrics = $this->getGoogleTransitMetrics($places, $travelMode);
                $path = $manualOrder ? array_keys($places)
                    : app(\App\Services\StopOrderOptimizer::class)->optimize($metrics, $preference);
                $openingPlan = null;
                if ($planningStart && collect($places)->contains(fn ($place) => !empty($place['operating_hours']))) {
                    $openingPlan = app(\App\Services\OpeningHoursOrderPlanner::class)->plan(
                        $places, $metrics, $path, $preference, $planningStart, $dailyStart, $dailyEnd, $lastDate
                    );
                    if (!$manualOrder && $openingPlan['best']) $path = $openingPlan['best']['path'];
                }
                $option = $this->buildRouteResult(
                    $places,
                    $metrics,
                    $preference,
                    $path,
                    0,
                    $travelMode
                );
                if ($manualOrder && $openingPlan && $openingPlan['best'] && $openingPlan['best']['path'] !== $path) {
                    $option['opening_suggested_order'] = array_map(fn ($index) => $places[$index]['name'], $openingPlan['best']['path']);
                    if (!$openingPlan['original']) {
                        $openingError = __('schedule.order_unavailable', ['sequence' => implode(' -> ', $option['opening_suggested_order'])]);
                        continue;
                    }
                }
                if ($travelMode === 'TRANSIT' && $option['total_fare'] === null) {
                    try {
                        $legs = $this->getTransitLegs($option['stops'], $travelMode, $planningStart, $lastDate, $dailyStart);
                        if (count($legs) === count($option['stops']) - 1
                            && collect($legs)->every(fn ($leg) => is_numeric($leg['fare'] ?? null))) {
                            $option['total_fare'] = round(collect($legs)->sum('fare'), 2);
                            $option['fare_currency'] = collect($legs)->pluck('fare_currency')->filter()->first() ?? 'MYR';
                            $option['fare_is_estimated'] = collect($legs)->contains(fn ($leg) => !empty($leg['fare_is_estimated']));
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
                $options[] = $option;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if ($options === [] && $openingError) throw new UnexpectedValueException($openingError);

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
            if ($manualOrder) {
                $option['description'] = __('schedule.manual_description');
            }
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

    private function transferFlags(array $place): array
    {
        $state = $place['state_name'] ?? null;
        $east = in_array($state, ['Sabah', 'Sarawak', 'Labuan'], true);
        return [
            'is_east' => $east,
            'is_west' => $state !== null && !$east,
            'is_island' => $this->usesIslandFerry([$place]),
        ];
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

    private function usesIslandFerry(array $places): bool
    {
        $islandKeywords = [
            'island', 'pulau', 'perhentian', 'rainforest beach', 'langkawi',
            'tioman', 'redang', 'pangkor', 'kapas', 'lang tengah', 'tenggol',
            'mabul', 'sipadan', 'rawa',
        ];

        foreach ($places as $place) {
            $searchable = strtolower(implode(' ', [
                $place['name'] ?? '',
                $place['location'] ?? '',
            ]));

            foreach ($islandKeywords as $keyword) {
                if (str_contains($searchable, $keyword)) {
                    return true;
                }
            }
        }

        return false;
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
                'operating_hours' => $places[$placeIndex]['operating_hours'] ?? null,
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

                if (in_array($travelMode, ['WALK', 'BICYCLE'], true)) {
                    $fares[$origin][$destination] = 0;
                    $fareCurrency ??= 'MYR';
                }
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
                        'routes.polyline.encodedPolyline',
                        'routes.legs.startLocation',
                        'routes.legs.endLocation',
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

            if (!is_array($response) || empty($response['legs']) || !isset($response['duration'])) {
                $message = __('messages.route_segment_unavailable', [
                    'from' => $from['name'],
                    'to' => $to['name'],
                    'time' => $departureTime->format('Y-m-d H:i'),
                    'mode' => $this->travelModeLabel($travelMode),
                ]);
                if ($travelMode === 'TRANSIT' && $initialDepartureTime !== null) {
                    if ($departureTime->isPast()) {
                        $message = __('messages.route_departure_past');
                    }
                    $suggestion = app(\App\Services\TransitDepartureSuggestion::class)->find($routeRequest, $departureTime);
                    if ($suggestion) {
                        $message .= ' '.__('messages.route_departure_suggestion', [
                            'time' => $suggestion->locale(app()->getLocale())->translatedFormat('j M Y, g:i A'),
                            'from' => $from['name'],
                            'to' => $to['name'],
                        ]);
                    }
                }
                throw new UnexpectedValueException($message);
            }

            $encodedPolylines = [];
            $tripSteps = [];
            $cursor = $departureTime;
            $currentLocation = $from['name'];

            $apiSteps = [];
            $estimatedFareTotal = 0.0;
            $hasEstimatedFare = false;
            $hasUnknownTransitFare = false;
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
                    $estimatedStepFare = $this->fareEstimator->estimate(
                        $line['nameShort'] ?? $line['name'] ?? $transport,
                        $line['vehicle']['type'] ?? '',
                        (int) ($step['distanceMeters'] ?? 0)
                    );
                    if ($estimatedStepFare !== null) {
                        $estimatedFareTotal += $estimatedStepFare;
                        $hasEstimatedFare = true;
                    } else {
                        $hasUnknownTransitFare = true;
                    }

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
                            'fare' => $estimatedStepFare,
                            'fare_currency' => $estimatedStepFare !== null ? 'MYR' : null,
                            'fare_is_estimated' => $estimatedStepFare !== null,
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

            if ($encodedPolylines === [] && !empty($response['polyline']['encodedPolyline'])) {
                $encodedPolylines[] = $response['polyline']['encodedPolyline'];
            }

            $durationSeconds = $this->durationToSeconds($response['duration'] ?? '0s');
            $arrivalTime = $departureTime->addSeconds($durationSeconds);
            $fare = $response['travelAdvisory']['transitFare'] ?? null;
            $officialFareValue = $fare !== null ? $this->moneyToFloat($fare) : null;
            // A zero transit fare must not suppress the existing fare estimate.
            $hasOfficialFare = $officialFareValue !== null && $officialFareValue > 0;
            $isWalkingOnly = $apiSteps !== [] && collect($apiSteps)->every(
                fn ($step) => ($step['travelMode'] ?? null) === 'WALK'
            );
            $usesEstimatedFare = !$hasOfficialFare && $hasEstimatedFare && !$hasUnknownTransitFare;
            $fareValue = $hasOfficialFare
                ? $officialFareValue
                : ($isWalkingOnly ? 0.0 : ($usesEstimatedFare ? $estimatedFareTotal : null));
            $tripSteps = $this->combineSimilarSteps($tripSteps);
            $transportParts = [];

            foreach ($tripSteps as $tripStep) {
                if ($tripStep['mode'] === 'WAIT') {
                    continue;
                }

                if (mb_strtolower(trim($tripStep['from'])) === mb_strtolower(trim($tripStep['to']))) {
                    continue;
                }

                if ($tripStep['mode'] === 'WALK') {
                    $part = __('route.walk_to', ['place' => $tripStep['to']]);
                } else {
                    $part = __('route_form.transport_between', [
                        'label' => $tripStep['label'],
                        'from' => $tripStep['from'],
                        'to' => $tripStep['to'],
                    ]);
                }

                if ($transportParts === [] || end($transportParts) !== $part) {
                    $transportParts[] = $part;
                }
            }

            $routeLegs = $response['legs'] ?? [];
            $firstRouteLeg = $routeLegs[0] ?? [];
            $lastRouteLeg = $routeLegs === [] ? [] : $routeLegs[array_key_last($routeLegs)];

            $legs[] = [
                'from' => $from['name'],
                'to' => $to['name'],
                'destination_place_id' => $to['place_id'] ?? null,
                'origin_latitude' => $firstRouteLeg['startLocation']['latLng']['latitude'] ?? $from['latitude'] ?? null,
                'origin_longitude' => $firstRouteLeg['startLocation']['latLng']['longitude'] ?? $from['longitude'] ?? null,
                'destination_latitude' => $lastRouteLeg['endLocation']['latLng']['latitude'] ?? $to['latitude'] ?? null,
                'destination_longitude' => $lastRouteLeg['endLocation']['latLng']['longitude'] ?? $to['longitude'] ?? null,
                'navigation_url' => $this->googleMapsNavigationUrl($to, $travelMode),
                'distance' => round(($response['distanceMeters'] ?? 0) / 1000, 2),
                'duration_minutes' => (int) ceil($durationSeconds / 60),
                'duration_seconds' => $durationSeconds,
                'duration_display' => $this->formatDuration($durationSeconds),
                'departure_time' => $this->formatTime($departureTime),
                'arrival_time' => $this->formatTime($arrivalTime),
                'arrival_at' => $arrivalTime->toIso8601String(),
                'trip_date' => $visitStartTime->toDateString(),
                'trip_date_display' => $visitStartTime
                    ->locale(app()->getLocale())
                    ->translatedFormat('D, d M Y'),
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
                'fare' => $fareValue !== null
                    ? round($fareValue, 2)
                    : null,
                'fare_currency' => $fareValue !== null
                    ? ($usesEstimatedFare ? 'MYR' : ($fare['currencyCode'] ?? 'MYR'))
                    : null,
                'fare_is_estimated' => $usesEstimatedFare,
                'is_walking_only' => $isWalkingOnly,
                'encoded_polylines' => $encodedPolylines,
            ];

            $departureTime = $arrivalTime;
        }

        return $legs;
    }

    /** Combine consecutive API fragments that describe the same movement. */
    private function combineSimilarSteps(array $steps): array
    {
        $combined = [];

        foreach ($steps as $step) {
            $lastIndex = array_key_last($combined);
            $last = $lastIndex !== null ? $combined[$lastIndex] : null;
            $sameMovement = $last
                && ($last['mode'] ?? null) === ($step['mode'] ?? null)
                && mb_strtolower(trim((string) ($last['label'] ?? ''))) === mb_strtolower(trim((string) ($step['label'] ?? '')))
                && mb_strtolower(trim((string) ($last['from'] ?? ''))) === mb_strtolower(trim((string) ($step['from'] ?? '')))
                && mb_strtolower(trim((string) ($last['to'] ?? ''))) === mb_strtolower(trim((string) ($step['to'] ?? '')));

            if (!$sameMovement) {
                $combined[] = $step;
                continue;
            }

            $distanceMeters = (int) ($last['distance_meters'] ?? 0) + (int) ($step['distance_meters'] ?? 0);
            $durationSeconds = (int) ($last['duration_seconds'] ?? 0) + (int) ($step['duration_seconds'] ?? 0);
            $combined[$lastIndex]['arrival_time'] = $step['arrival_time'];
            $combined[$lastIndex]['distance_meters'] = $distanceMeters;
            $combined[$lastIndex]['duration_seconds'] = $durationSeconds;
            $combined[$lastIndex]['distance'] = $distanceMeters >= 1000
                ? __('route_form.distance_km', ['distance' => number_format($distanceMeters / 1000, 1)])
                : __('route_form.distance_m', ['distance' => $distanceMeters]);
            $combined[$lastIndex]['duration'] = $this->formatDuration($durationSeconds);
        }

        return $combined;
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
        if ($savedSource && $collectionId) {
            $collectionPlaces = collect($this->collectionPlaces($this->collectionForCurrentUser($collectionId)))->keyBy('route_key');
            if ($destinationKeys === []) {
                $destinationKeys = $collectionPlaces->keys()->all();
            }
            $places = [];
            foreach ($destinationKeys as $key) {
                $places[] = $collectionPlaces->has($key)
                    ? $collectionPlaces->get($key)
                    : $this->placesFromDestinationKeys([$key], true)[0];
            }
            if (count($places) < 2) {
                throw new UnexpectedValueException(__('route.choose_two'));
            }
            $identities = array_map(fn ($place) => $place['place_id'], $places);
            if (count(array_unique($identities)) !== count($identities)) {
                throw new UnexpectedValueException(__('route.duplicate'));
            }
            return $places;
        }
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
                    'operating_hours' => \App\Models\Attraction::where('attraction_name', self::PLACES[$placeIndex]['name'])->value('operating_hours'),
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
                'location' => $wishlist->attraction->location,
                'route_key' => $destinationKey,
                'operating_hours' => $wishlist->attraction->operating_hours,
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
            'duration_seconds' => $arrival->diffInSeconds($departure),
            'distance_meters' => $distanceMeters,
            'distance' => $distanceMeters >= 1000
                ? __('route_form.distance_km', ['distance' => number_format($distanceMeters / 1000, 1)])
                : __('route_form.distance_m', ['distance' => $distanceMeters]),
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

    private function collectionPlaces(SavedPlaceCollection $collection): array
    {
        return $collection->items()->with('attraction.state')->orderBy('collection_item_id')->get()
            ->filter(fn ($item) => $item->attraction !== null)
            ->map(fn ($item): array => [
                'name' => $item->attraction->attraction_name,
                'place_id' => $item->attraction->place_id,
                'location' => $item->attraction->location,
                'route_key' => 'collection:'.$item->collection_item_id,
                'operating_hours' => $item->attraction->operating_hours,
                'category' => $item->attraction->category,
                'state_name' => $item->attraction->state?->state_name,
            ])->values()->all();
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

        $parts = [];

        if ($hours > 0) {
            $parts[] = trans_choice('route_form.duration_hour', $hours, ['count' => $hours]);
        }

        if ($remainingMinutes > 0 || $parts === []) {
            $parts[] = trans_choice('route_form.duration_minute', $remainingMinutes, ['count' => $remainingMinutes]);
        }

        return implode(' ', $parts);
    }

    private function formatDuration(float $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);
        $days = intdiv($minutes, 1440);
        $minutesAfterDays = $minutes % 1440;
        $hours = intdiv($minutesAfterDays, 60);
        $remainingMinutes = $minutesAfterDays % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = trans_choice('route_form.duration_day', $days, ['count' => $days]);
        }

        $parts[] = trans_choice('route_form.duration_hour', $hours, ['count' => $hours]);
        $parts[] = trans_choice('route_form.duration_minute', $remainingMinutes, ['count' => $remainingMinutes]);

        return implode(' ', $parts);
    }
}
