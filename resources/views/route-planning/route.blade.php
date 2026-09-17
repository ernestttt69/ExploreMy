@extends('layouts.app')

@section('title', __('route.title'))

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/route-planning.css') }}?v={{ filemtime(public_path('css/route-planning.css')) }}"
>
@endpush

@section('content')
<div class="route-page">
    <p class="route-description">
        {{ __('route.description') }}
    </p>

    @if($usingSavedPlaces)
        <section class="saved-route-summary">
            <span class="saved-places-label">
                <span class="saved-places-icon" aria-hidden="true">&#9825;</span>
                {{ $collection ? $collection->name : __('route.saved') }}
            </span>
            <h2>{{ __('route.destinations_ready', ['count' => $savedPlacesCount]) }}</h2>
            <div>
                @foreach($savedPlaces->take(8) as $savedPlace)
                    <span>{{ $savedPlace->attraction->attraction_name }}</span>
                @endforeach
            </div>
            @if($savedPlacesCount > 8)
                <small>{{ __('route_form.more_places_available', ['count' => $savedPlacesCount - 8]) }}</small>
            @elseif($savedPlacesCount < 2)
                <small>{{ __('route.need_two') }}</small>
            @endif
        </section>

            <aside class="flight-recommendation" role="note" data-flight-notice @if(!$requiresFlight) hidden @endif>
                <strong>{{ __('route_form.flight_required') }}</strong>
                <span>{{ __('route_form.flight_required_description') }}</span>
            </aside>
            <aside class="flight-recommendation" role="note" data-ferry-notice @if(!$requiresFerry) hidden @endif>
                <strong>{{ __('route_form.ferry_required') }}</strong>
                <span>{{ __('route_form.ferry_required_description') }}</span>
            </aside>
    @endif

    @if (session('success'))
        <div class="route-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="route-error">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="route-workspace {{ session('routeResult') ? 'has-route-result' : '' }}">
    @if (session('routeResult'))
        @php $routeResult = session('routeResult'); @endphp
        @php $routeLegs = collect($routeResult['transit_legs'] ?? []); @endphp
        @php $isFlightOnlyRoute = $routeLegs->isNotEmpty() && $routeLegs->every(fn ($leg) => !empty($leg['is_cross_region_transfer'])); @endphp
        <section class="route-result">
            <div class="card-heading">
                <h2>{{ $routeResult['title'] }}</h2>
                <p>{{ __('schedule.suggested_order') }}</p>
                <span class="route-mode-badge">
                    {{ $routeResult['option_label'] }}
                </span>
                <p class="route-trip-dates">
                    {{ \Carbon\CarbonImmutable::parse($routeResult['trip_start_date'])->locale(app()->getLocale())->translatedFormat('d M Y') }}
                    @if (($routeResult['trip_day_count'] ?? 1) > 1)
                        &ndash; {{ \Carbon\CarbonImmutable::parse($routeResult['trip_end_date'])->locale(app()->getLocale())->translatedFormat('d M Y') }}
                        ({{ __('route_form.days', ['count' => $routeResult['trip_day_count']]) }})
                    @else
                        ({{ __('route_form.one_day') }})
                    @endif
                </p>
                @if(!empty($routeResult['opening_conflicts']))
                    <div class="route-omitted-warning" role="status">
                        @foreach($routeResult['opening_conflicts'] as $conflict)
                            <p>{{ __('schedule.opening_conflict', [
                                'place' => $conflict['place'],
                                'arrival' => \Carbon\CarbonImmutable::parse($conflict['arrival_at'])->format('d M, g:i A'),
                                'available' => \Carbon\CarbonImmutable::parse($conflict['available_at'])->format('d M, g:i A'),
                            ]) }}</p>
                        @endforeach
                        @if(!empty($routeResult['opening_suggested_order']))
                            <p>{{ __('schedule.suggest_open_order', ['sequence' => implode(' → ', $routeResult['opening_suggested_order'])]) }}</p>
                        @endif
                    </div>
                @endif
                @if(!empty($routeResult['omitted_places']))
                    <div class="route-omitted-warning">
                        <strong>{{ __('route.omitted') }}</strong>
                        {{ implode(', ', $routeResult['omitted_places']) }}.
                        {{ __('route.cannot_connect') }}
                    </div>
                @endif
                @if(!empty($routeResult['fallback_notice']))
                    <div class="route-fallback-warning">
                        {{ $routeResult['fallback_notice'] }}
                    </div>
                @endif
            </div>

            @if (session('routeOptions') && ($routeResult['travel_mode'] ?? null) !== 'MIXED')
                <div class="route-options-heading">
                    <div>
                        <strong>{{ __('route.options_found', ['count' => count(session('routeOptions'))]) }}</strong>
                        <span>{{ __('route_form.compare_options') }}</span>
                    </div>
                </div>

                <div class="route-option-grid">
                    @foreach (session('routeOptions') as $optionIndex => $option)
                        <article class="route-option-card {{ $routeResult['option_index'] === $optionIndex ? 'is-selected' : '' }}">
                            <div class="route-option-header">
                                <div>
                                    <span class="route-number">{{ __('route.route_number', ['number' => $loop->iteration]) }}</span>
                                    @if ($routeResult['option_index'] === $optionIndex)
                                        <span class="route-badge">{{ __('route.selected') }}</span>
                                    @endif
                                </div>
                                <strong>{{ $option['option_label'] }}</strong>
                            </div>

                            <div class="route-metrics">
                                <div>
                                    <span>{{ __('route.total_time') }}</span>
                                    <strong>{{ $option['total_duration_display'] }}</strong>
                                </div>
                                    <div>
                                        <span>{{ __('route.total_fare') }}</span>
                                        <strong>
                                            @if ($option['total_fare'] !== null)
                                            {{ $option['fare_currency'] ?? 'MYR' }} {{ number_format($option['total_fare'], 2) }}
                                            @if(!empty($option['fare_is_estimated']))
                                                ({{ __('route_form.fare_estimated') }})
                                            @endif
                                            @else
                                                {{ __('route.fare_unavailable') }}
                                            @endif
                                        </strong>
                                    </div>
                                <div>
                                    <span>{{ __('route.distance') }}</span>
                                    <strong>{{ __('route_form.distance_km', ['distance' => number_format($option['total_distance'], 2)]) }}</strong>
                                </div>
                            </div>

                            <ol class="route-option-timeline">
                                @foreach ($option['stops'] as $stop)
                                    <li>
                                        <span class="timeline-dot">{{ $loop->iteration }}</span>
                                        <div>
                                            <strong>{{ $stop['name'] }}</strong>
                                            <small>
                                                {{ $loop->first
                                                    ? __('route.starting_point')
                                                    : __('route.from_previous', ['distance' => number_format($stop['distance_from_previous'], 2)]) }}
                                            </small>
                                            <small class="stop-visit-suggestion">
                                                {{ __('route_form.suggested_visit', ['duration' => $stop['suggested_visit_display']]) }}
                                            </small>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>

                            @if ($routeResult['option_index'] === $optionIndex)
                                <button type="button" class="select-route-button selected-route" disabled>
                                    {{ __('route.selected_route') }}
                                </button>
                            @else
                                <form action="{{ route('route.preference') }}" method="POST">
                                    @csrf
            @if($usingSavedPlaces)<input type="hidden" name="source" value="saved">@endif
            @if($collection)<input type="hidden" name="collection_id" value="{{ $collection->collection_id }}">@endif
                                    <input type="hidden" name="order_mode" value="{{ $routeResult['order_mode'] ?? 'auto' }}">
                                    <input type="hidden" name="optimization_preference" value="{{ $routeResult['preference'] }}">
                                    <input type="hidden" name="route_option_index" value="{{ $optionIndex }}">
                                    <input type="hidden" name="travel_mode" value="{{ $option['travel_mode'] }}">
                                    @if(!empty($routeResult['trip_end_time']))
                                        <input type="hidden" name="end_time" value="{{ $routeResult['trip_end_time'] }}">
                                    @endif
                                    @if(!empty($routeResult['trip_start_time']))
                                        <input type="hidden" name="start_time" value="{{ $routeResult['trip_start_time'] }}">
                                    @endif
                                    @foreach ($option['stops'] as $stop)
                                        <input type="hidden" name="destination_keys[]" value="{{ $stop['route_key'] }}">
                                    @endforeach
                                    <button type="submit" class="select-route-button">
                                        {{ __('route.select_route') }} &rarr;
                                    </button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            @if (($routeResult['travel_mode'] ?? null) === 'MIXED')
                @php $transferLeg = collect($routeResult['transit_legs'])->firstWhere('is_cross_region_transfer', true); @endphp
                @php $isFerryTransfer = ($transferLeg['transfer_type'] ?? null) === 'FERRY'; @endphp
                <section class="cross-region-transfer-card">
                    <div class="transfer-icon" aria-hidden="true">{!! $isFerryTransfer ? '&#9972;' : '&#9992;' !!}</div>
                    <div class="transfer-copy">
                        <span class="transfer-kicker">{{ __($isFerryTransfer ? 'route_form.ferry_transfer' : 'route_form.cross_region_transfer') }}</span>
                        <div class="transfer-route">
                            <strong>{{ $transferLeg['from'] }}</strong>
                            <span class="transfer-line"><i></i><b>{!! $isFerryTransfer ? '&#9972;' : '&#9992;' !!}</b><i></i></span>
                            <strong>{{ $transferLeg['to'] }}</strong>
                        </div>
                        <p>{{ __($isFerryTransfer ? 'route_form.ferry_transfer_description' : 'route_form.transfer_description') }}</p>
                        <small>{{ __($isFerryTransfer ? 'route_form.ferry_transfer_warning' : 'route_form.transfer_warning') }}</small>
                    </div>
                    <a href="{{ $isFerryTransfer ? 'https://www.malaysiaferry.com/ferry' : 'https://www.google.com/travel/flights' }}" target="_blank" rel="noopener noreferrer">
                        {{ __($isFerryTransfer ? 'route_form.check_ferries' : 'route_form.check_flights') }}
                    </a>
                </section>
            @endif

            @if ($googleMapsBrowserKey && !$isFlightOnlyRoute)
                <div
                    id="route-map"
                    class="route-map"
                    role="img"
                    aria-label="{{ __('route.map_aria', ['mode' => $routeResult['option_label']]) }}"
                ></div>
            @elseif (($routeResult['travel_mode'] ?? null) !== 'MIXED')
                <div class="route-map-warning">
                    {{ __('route.map_key') }}
                </div>
            @endif

            <div class="trip-overview">
                <div>
                    <span>{{ __('route.start') }}</span>
                    <strong>{{ $routeResult['stops'][0]['name'] }}</strong>
                    <small>{{ $routeResult['departure_time'] }}</small>
                </div>
                <div class="trip-overview-arrow">&rarr;</div>
                <div>
                    <span>{{ __('route.final_stop') }}</span>
                    <strong>{{ $routeResult['stops'][count($routeResult['stops']) - 1]['name'] }}</strong>
                    <small>{{ $routeResult['arrival_time'] }}</small>
                </div>
                <div>
                    <span>{{ __('route.total_time') }}</span>
                    <strong>{{ $routeResult['total_duration_display'] }}</strong>
                </div>
            </div>

            <div class="transit-legs">
                <h3>
                    {{ $routeResult['option_label'] }} {{ __('route.guidance') }}
                </h3>
                @if(isset($routeResult['stops'][0]['visit_start_at']))
                    @include('route-planning.daily-timeline')
                @else
                @foreach ($routeResult['transit_legs'] as $leg)
                    <section class="transit-leg">
                        <div class="transit-leg-heading">
                            <div>
                                <strong>{{ $leg['from'] }} &rarr; {{ $leg['to'] }}</strong>
                                <span>
                                    @if(isset($routeResult['stops'][$loop->index]['visit_start_at']))
                                        {{ \Carbon\CarbonImmutable::parse($routeResult['stops'][$loop->index]['visit_start_at'])->format('d M, g:i A') }}
                                        &ndash; {{ \Carbon\CarbonImmutable::parse($leg['arrival_at'])->format('d M, g:i A') }}
                                    @else
                                        {{ $leg['trip_date_display'] }} &middot; {{ $leg['visit_start_time'] }}–{{ $leg['arrival_time'] }}
                                    @endif
                                </span>
                            </div>
                            <strong>{{ $leg['segment_duration_display'] }}</strong>
                        </div>

                        <div class="simple-trip-timeline">
                            <div class="simple-trip-stop">
                                <time>{{ isset($routeResult['stops'][$loop->index]['visit_start_at']) ? \Carbon\CarbonImmutable::parse($routeResult['stops'][$loop->index]['visit_start_at'])->format('d M, g:i A') : $leg['visit_start_time'] }}</time>
                                <span class="simple-trip-dot"></span>
                                <div>
                                    <strong>{{ $leg['from'] }}</strong>
                                    <small class="stop-visit-suggestion">
                                        {{ __('route_form.explore_until', ['time' => $leg['visit_end_time'], 'duration' => $leg['visit_duration_display']]) }}
                                    </small>
                                </div>
                            </div>

                            <div class="simple-trip-transport transport-with-fare">
                                <div class="transport-details">
                                <small>{{ $leg['trip_date_display'] }} &middot; {{ $leg['departure_time'] }}</small>
                                <span>{{ $leg['transport_summary'] }}</span>
                                <small>
                                    @if(!empty($leg['is_cross_region_transfer']))
                                        {{ __('route_form.estimated_transfer') }}
                                    @else
                                        {{ $leg['duration_display'] }}
                                        &middot; {{ __('route_form.distance_km', ['distance' => number_format($leg['distance'], 2)]) }}
                                    @endif
                                </small>
                                </div>
                                @if ($leg['fare'] !== null && empty($leg['is_walking_only']))
                                    <strong class="transport-fare">{{ $leg['fare_currency'] }} {{ number_format($leg['fare'], 2) }} @if(!empty($leg['fare_is_estimated'])) ({{ __('route_form.fare_estimated') }}) @endif</strong>
                                @endif
                            </div>

                            <div class="simple-trip-stop">
                                <time>{{ $leg['arrival_time'] }}</time>
                                <span class="simple-trip-dot"></span>
                                <div>
                                    <strong>{{ __('route.arrive', ['place' => $leg['to']]) }}</strong>
                                    @if($loop->last)
                                        <small class="stop-visit-suggestion">
                                            @if(isset(last($routeResult['stops'])['visit_start_at']))
                                                {{ \Carbon\CarbonImmutable::parse(last($routeResult['stops'])['visit_start_at'])->format('d M, g:i A') }} &middot;
                                            @endif
                                            {{ __('route_form.explore_until', ['time' => $routeResult['final_visit_end_time'], 'duration' => last($routeResult['stops'])['suggested_visit_display']]) }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="transit-leg-actions">
                            @if(empty($leg['is_cross_region_transfer']))
                                <a
                                    class="guidance-button navigation-button"
                                    href="{{ $leg['navigation_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ __('route_form.navigate_google_maps') }}
                                </a>
                            @endif
                        </div>
                    </section>
                @endforeach
                @endif
                <div class="journey-guidance-action">
                    <button type="button" class="guidance-button" data-guidance-open="journey-guidance">
                        {{ __('route.view_guidance') }}
                    </button>
                </div>
            </div>

            <div id="journey-guidance" class="guidance-modal" data-guidance-modal role="dialog" aria-modal="true" aria-labelledby="journey-guidance-title" hidden>
                <div class="guidance-dialog">
                    <div class="guidance-dialog-heading">
                        <div>
                            <span>{{ __('route.guidance') }}</span>
                            <h3 id="journey-guidance-title">{{ $routeResult['stops'][0]['name'] }} &rarr; {{ last($routeResult['stops'])['name'] }}</h3>
                        </div>
                        <button type="button" class="guidance-close" data-guidance-close aria-label="{{ __('route.close_guidance') }}"></button>
                    </div>
                    <ol class="guidance-list journey-guidance-list">
                        @php $guidanceStepNumber = 0; @endphp
                        @foreach ($routeResult['transit_legs'] as $leg)
                            @foreach ($leg['steps'] as $step)
                                @php $guidanceStepNumber++; @endphp
                                <li>
                                    <span class="guidance-step-number">{{ $guidanceStepNumber }}</span>
                                    <div>
                                        <strong>{{ $step['label'] }}</strong>
                                        <span>{{ $step['from'] }} &rarr; {{ $step['to'] }}</span>
                                        <small>
                                            {{ $step['departure_time'] }}–{{ $step['arrival_time'] }}
                                            &middot; {{ $step['duration'] }}
                                            &middot; {{ $step['distance'] }}
                                        </small>
                                        @if (!empty($step['headsign']))
                                            <small>{{ __('route.headsign', ['headsign' => $step['headsign']]) }}</small>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        @endforeach
                    </ol>
                </div>
            </div>

            <div class="route-total">
                @if (($routeResult['travel_mode'] ?? null) !== 'MIXED')
                    <span>{{ __('route.total_distance') }}</span>
                    <strong>{{ __('route_form.distance_km', ['distance' => number_format($routeResult['total_distance'], 2)]) }}</strong>
                @endif
                <span>{{ __('route.estimated_time') }}</span>
                <strong>{{ $routeResult['total_duration_display'] }}</strong>
                @if (($routeResult['travel_mode'] ?? 'TRANSIT') !== 'DRIVE' && $routeResult['total_fare'] !== null)
                    <span>{{ __('route.estimated_fare') }}</span>
                    <strong>
                        {{ $routeResult['fare_currency'] }} {{ number_format($routeResult['total_fare'], 2) }}
                        @if(!empty($routeResult['fare_is_estimated']))
                            ({{ __('route_form.fare_estimated') }})
                        @endif
                    </strong>
                @endif
            </div>
            <div class="save-itinerary-panel">
                <span class="save-itinerary-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M6 3.75h12a1.25 1.25 0 0 1 1.25 1.25v15.25L12 16.1l-7.25 4.15V5A1.25 1.25 0 0 1 6 3.75Z" />
                    </svg>
                </span>
                <div class="save-itinerary-copy">
                    <strong>{{ __('itinerary.save_heading') }}</strong>
                    <span>{{ __('itinerary.save_text') }}</span>
                </div>
                <form method="POST" action="{{ route('itineraries.store-generated-route') }}">
                    @csrf
                    <button type="submit" class="save-itinerary-button">{{ __('itinerary.save_button') }}</button>
                </form>
            </div>
        </section>
    @endif

    <form
        action="{{ route('route.preference') }}"
        method="POST"
    >
        @csrf

        @if($usingSavedPlaces)
            <input type="hidden" name="source" value="saved">
            @if($collection)<input type="hidden" name="collection_id" value="{{ $collection->collection_id }}">@endif
        @endif

        <section class="preference-card">
            <div class="card-heading">
                <h2>{{ __('route.preference') }}</h2>
                <p>{{ __('route.optimise') }}</p>
            </div>
            <p class="google-attribution">{{ __('route_form.google_attribution', ['year' => date('Y')]) }}</p>

            <div class="route-start-time-field">
                <label for="route-start-time">{{ __('route_form.daily_starting_time') }} <small>({{ __('route_form.optional') }})</small></label>
                <input type="time" id="route-start-time" name="start_time" value="{{ old('start_time', session('routeResult.trip_start_time', $collection?->start_time ? substr($collection->start_time, 0, 5) : '')) }}">
                @if(!$collection)<small>{{ __('route_form.start_time_help') }}</small>@endif
                @error('start_time')<span class="route-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="route-start-time-field">
                <label for="route-end-time">{{ __('schedule.end_time') }} <small>({{ __('route_form.optional') }})</small></label>
                <input type="time" id="route-end-time" name="end_time" value="{{ old('end_time', session('routeResult.trip_end_time', $collection?->end_time ? substr($collection->end_time, 0, 5) : '')) }}">
                @error('end_time')<span class="route-field-error">{{ $message }}</span>@enderror
            </div>

            @php $selectedPreference = old('optimization_preference', 'fastest'); @endphp
            <input type="hidden" name="order_mode" value="{{ old('order_mode', session('routeResult.order_mode', 'auto')) }}" data-order-mode>
            <div class="itinerary-editor" data-itinerary-editor>
                <div class="itinerary-editor-heading">
                    <div>
                        <strong>{{ __('route.destinations') }}</strong>
                        <small>{{ __('route.arrange') }}</small>
                    </div>
                    <span data-destination-count>{{ __('route.stops', ['count' => count($selectedDestinationKeys)]) }}</span>
                </div>

                <ol class="itinerary-list" data-itinerary-list>
                    @foreach ($selectedDestinationKeys as $destinationKey)
                        @php $destination = collect($availablePlaces)->firstWhere('route_key', $destinationKey); @endphp
                        @if ($destination)
                            <li class="itinerary-item" data-destination-key="{{ $destination['route_key'] }}">
                                <input type="hidden" name="destination_keys[]" value="{{ $destination['route_key'] }}">
                                <span class="itinerary-position">{{ $loop->iteration }}</span>
                                <strong>{{ $destination['name'] }}</strong>
                                <div class="itinerary-actions">
                                    <button type="button" data-move="up" aria-label="{{ __('route.move_up', ['name' => $destination['name']]) }}">&uarr;</button>
                                    <button type="button" data-move="down" aria-label="{{ __('route.move_down', ['name' => $destination['name']]) }}">&darr;</button>
                                    <button type="button" data-remove aria-label="{{ __('route.remove', ['name' => $destination['name']]) }}">&times;</button>
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ol>

                <div class="itinerary-add">
                    <label for="add-destination">{{ __('route.add_destination') }}</label>
                    @if($usingSavedPlaces)
                        <div class="saved-place-batch-picker">
                            <input
                                type="search"
                                data-saved-place-search
                                data-search-url="{{ route('route.saved-places.search') }}"
                                data-collection-id=""
                                placeholder="{{ __('route_form.search_placeholder') }}"
                                autocomplete="off"
                            >
                            <small data-saved-search-status>{{ __('route_form.showing_saved_places') }}</small>
                            <select id="add-destination" data-add-destination multiple hidden aria-hidden="true">
                                @foreach ($availablePlaces as $destination)
                                    <option value="{{ $destination['route_key'] }}" data-name="{{ $destination['name'] }}">
                                        {{ $destination['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="saved-place-checkboxes" data-saved-place-checkboxes role="group" aria-label="{{ __('route.add_destination') }}">
                                @foreach ($availablePlaces as $destination)
                                    <label>
                                        <input type="checkbox" value="{{ $destination['route_key'] }}" data-name="{{ $destination['name'] }}">
                                        <span>{{ $destination['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <select id="add-destination" data-add-destination>
                            <option value="">{{ __('route.choose_place') }}</option>
                            @foreach ($availablePlaces as $destination)
                                <option value="{{ $destination['route_key'] }}" data-name="{{ $destination['name'] }}">
                                    {{ $destination['name'] }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" data-add-stop>{{ $usingSavedPlaces ? __('route_form.add_selected_stops') : __('route.add_stop') }}</button>
                </div>
                <p class="itinerary-hint" data-itinerary-hint>{{ __('route.choose_two') }}</p>
            </div>

            <div class="preference-list">
                <label class="preference-option {{ $selectedPreference === 'fastest' ? 'selected' : '' }}">
                    <input
                        type="radio"
                        name="optimization_preference"
                        value="fastest"
                        @checked($selectedPreference === 'fastest')
                    >

                    <span class="custom-radio"></span>

                    <span class="preference-icon">⚡</span>

                    <span class="preference-text">
                        <strong>{{ __('route.fastest') }}</strong>
                        <small>{{ __('route.fastest_desc') }}</small>
                    </span>
                </label>

                <label class="preference-option {{ $selectedPreference === 'shortest' ? 'selected' : '' }}">
                    <input
                        type="radio"
                        name="optimization_preference"
                        value="shortest"
                        @checked($selectedPreference === 'shortest')
                    >

                    <span class="custom-radio"></span>

                    <span class="preference-icon">📏</span>

                    <span class="preference-text">
                        <strong>{{ __('route.shortest') }}</strong>
                        <small>{{ __('route.shortest_desc') }}</small>
                    </span>
                </label>

                <label class="preference-option {{ $selectedPreference === 'lowest_cost' ? 'selected' : '' }}">
                    <input
                        type="radio"
                        name="optimization_preference"
                        value="lowest_cost"
                        @checked($selectedPreference === 'lowest_cost')
                    >

                    <span class="custom-radio"></span>

                    <span class="preference-icon">💰</span>

                    <span class="preference-text">
                        <strong>{{ __('route.lowest') }}</strong>
                        <small>{{ __('route.lowest_desc') }}</small>
                    </span>
                </label>
            </div>
        </section>

        <button type="submit" class="continue-button" @disabled($usingSavedPlaces && $savedPlacesCount < 2)>
            {{ __('route.continue') }}
        </button>
    </form>
    </div>
</div>
@endsection

@push('scripts')
@php
$routeTranslations = [
    'stops' => __('route.stops', ['count' => ':count']),
    'moveUp' => __('route.move_destination_up'),
    'moveDown' => __('route.move_destination_down'),
    'remove' => __('route.remove_destination'),
    'choosePlace' => __('route.choose_place'),
    'shareText' => __('route.share_text'),
    'searching' => __('route_form.searching'),
    'matchingLoaded' => __('route_form.matching_loaded'),
    'noSavedPlaces' => __('route_form.no_saved_places'),
    'loadFailed' => __('route_form.load_failed'),
];
@endphp
<script type="application/json" id="route-translations-data">{!! json_encode($routeTranslations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="route-place-flags-data">{!! json_encode(collect($availablePlaces)->keyBy('route_key')->all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<script>
window.routeTranslations = JSON.parse(document.getElementById('route-translations-data').textContent);
window.routePlaceFlags = JSON.parse(document.getElementById('route-place-flags-data').textContent);
</script>
<script src="{{ asset('js/route-planning.js') }}?v={{ filemtime(public_path('js/route-planning.js')) }}"></script>
@if (session('routeResult') && $googleMapsBrowserKey && !($isFlightOnlyRoute ?? false))
    <script type="application/json" id="route-map-data">
        {!! json_encode(session('routeResult'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
    </script>
    <script
        async
        src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsBrowserKey) }}&libraries=geometry&callback=initRouteMap"
    ></script>
@endif
@endpush
