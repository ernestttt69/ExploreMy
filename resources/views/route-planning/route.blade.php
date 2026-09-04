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
                <small>{{ __('route_form.up_to_eight') }}</small>
            @elseif($savedPlacesCount < 2)
                <small>{{ __('route.need_two') }}</small>
            @endif
        </section>

        @if($requiresFlight || session('flightRequired'))
            <aside class="flight-recommendation" role="note">
                <strong>{{ __('route_form.flight_required') }}</strong>
                <span>{{ __('route_form.flight_required_description') }}</span>
            </aside>
        @endif
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
        @php($routeResult = session('routeResult'))
        @php($routeLegs = collect($routeResult['transit_legs'] ?? []))
        @php($isFlightOnlyRoute = $routeLegs->isNotEmpty() && $routeLegs->every(fn ($leg) => !empty($leg['is_cross_region_transfer'])))
        <section class="route-result">
            <div class="card-heading">
                <h2>{{ $routeResult['title'] }}</h2>
                <p>{{ $routeResult['description'] }}</p>
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
                                @if (($routeResult['travel_mode'] ?? 'TRANSIT') !== 'DRIVE' && $option['total_fare'] !== null)
                                    <div>
                                        <span>{{ __('route.total_fare') }}</span>
                                        <strong>{{ $option['fare_currency'] }} {{ number_format($option['total_fare'], 2) }}</strong>
                                    </div>
                                @endif
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
                                    <input type="hidden" name="optimization_preference" value="{{ $routeResult['preference'] }}">
                                    <input type="hidden" name="route_option_index" value="{{ $optionIndex }}">
                                    <input type="hidden" name="travel_mode" value="{{ $option['travel_mode'] }}">
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
                @php($transferLeg = collect($routeResult['transit_legs'])->firstWhere('is_cross_region_transfer', true))
                <section class="cross-region-transfer-card">
                    <div class="transfer-icon" aria-hidden="true">&#9992;</div>
                    <div class="transfer-copy">
                        <span class="transfer-kicker">{{ __('route_form.cross_region_transfer') }}</span>
                        <div class="transfer-route">
                            <strong>{{ $transferLeg['from'] }}</strong>
                            <span class="transfer-line"><i></i><b>&#9992;</b><i></i></span>
                            <strong>{{ $transferLeg['to'] }}</strong>
                        </div>
                        <p>{{ __('route_form.transfer_description') }}</p>
                        <small>{{ __('route_form.transfer_warning') }}</small>
                    </div>
                    <a href="https://www.google.com/travel/flights" target="_blank" rel="noopener noreferrer">
                        {{ __('route_form.check_flights') }}
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
                @foreach ($routeResult['transit_legs'] as $leg)
                    <section class="transit-leg">
                        <div class="transit-leg-heading">
                            <div>
                                <strong>{{ $leg['from'] }} &rarr; {{ $leg['to'] }}</strong>
                                <span>{{ $leg['trip_date_display'] }} &middot; {{ $leg['visit_start_time'] }}–{{ $leg['arrival_time'] }}</span>
                            </div>
                            <strong>{{ $leg['segment_duration_display'] }}</strong>
                        </div>

                        <div class="simple-trip-timeline">
                            <div class="simple-trip-stop">
                                <time>{{ $leg['visit_start_time'] }}</time>
                                <span class="simple-trip-dot"></span>
                                <div>
                                    <strong>{{ $leg['from'] }}</strong>
                                    <small class="stop-visit-suggestion">
                                        {{ __('route_form.explore_until', ['time' => $leg['visit_end_time'], 'duration' => $leg['visit_duration_display']]) }}
                                    </small>
                                </div>
                            </div>

                            <div class="simple-trip-transport">
                                <span>{{ $leg['transport_summary'] }}</span>
                                <small>
                                    @if(!empty($leg['is_cross_region_transfer']))
                                        {{ __('route_form.estimated_transfer') }}
                                    @else
                                        {{ $leg['duration_display'] }}
                                        &middot; {{ __('route_form.distance_km', ['distance' => number_format($leg['distance'], 2)]) }}
                                        @if (($routeResult['travel_mode'] ?? 'TRANSIT') !== 'DRIVE' && $leg['fare'] !== null)
                                            &middot;
                                            {{ $leg['fare_currency'] }} {{ number_format($leg['fare'], 2) }}
                                        @endif
                                    @endif
                                </small>
                            </div>

                            <div class="simple-trip-stop">
                                <time>{{ $leg['arrival_time'] }}</time>
                                <span class="simple-trip-dot"></span>
                                <div>
                                    <strong>{{ __('route.arrive', ['place' => $leg['to']]) }}</strong>
                                    @if($loop->last)
                                        <small class="stop-visit-suggestion">
                                            {{ __('route_form.explore_until', ['time' => $routeResult['final_visit_end_time'], 'duration' => last($routeResult['stops'])['suggested_visit_display']]) }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="transit-leg-actions">
                            <button
                                type="button"
                                class="guidance-button"
                                data-guidance-open="guidance-{{ $loop->index }}"
                            >
                                {{ __('route.view_guidance') }}
                            </button>
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
            </div>

            @foreach ($routeResult['transit_legs'] as $leg)
                <div
                    id="guidance-{{ $loop->index }}"
                    class="guidance-modal"
                    data-guidance-modal
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="guidance-title-{{ $loop->index }}"
                    hidden
                >
                    <div class="guidance-dialog">
                        <div class="guidance-dialog-heading">
                            <div>
                                <span>{{ __('route.guidance') }}</span>
                                <h3 id="guidance-title-{{ $loop->index }}">{{ $leg['from'] }} &rarr; {{ $leg['to'] }}</h3>
                            </div>
                            <button type="button" class="guidance-close" data-guidance-close aria-label="{{ __('route.close_guidance') }}"></button>
                        </div>
                        <ol class="guidance-list">
                            @foreach ($leg['steps'] as $step)
                                <li>
                                    <span class="guidance-step-number">{{ $loop->iteration }}</span>
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
                        </ol>
                    </div>
                </div>
            @endforeach

            <div class="route-total">
                @if (($routeResult['travel_mode'] ?? null) !== 'MIXED')
                    <span>{{ __('route.total_distance') }}</span>
                    <strong>{{ __('route_form.distance_km', ['distance' => number_format($routeResult['total_distance'], 2)]) }}</strong>
                @endif
                <span>{{ __('route.estimated_time') }}</span>
                <strong>{{ $routeResult['total_duration_display'] }}</strong>
                @if (($routeResult['travel_mode'] ?? 'TRANSIT') !== 'DRIVE' && $routeResult['total_fare'] !== null)
                    <span>{{ __('route.estimated_fare') }}</span>
                    <strong>{{ $routeResult['fare_currency'] }} {{ number_format($routeResult['total_fare'], 2) }}</strong>
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

            @if($collection)
                <div class="route-start-time-summary">
                    <span>{{ __('route_form.daily_starting_time') }}</span>
                    <strong>{{ \Carbon\CarbonImmutable::parse($collection->start_time ?: '09:00')->format('g:i A') }}</strong>
                </div>
            @else
                <div class="route-start-time-field">
                    <label for="route-start-time">{{ __('route_form.trip_starting_time') }} <small>({{ __('route_form.optional') }})</small></label>
                    <input
                        type="time"
                        id="route-start-time"
                        name="start_time"
                        value="{{ old('start_time', session('routeResult')['trip_start_time'] ?? '') }}"
                    >
                    <small>{{ __('route_form.start_time_help') }}</small>
                    @error('start_time')<span class="route-field-error">{{ $message }}</span>@enderror
                </div>
            @endif

            @php($selectedPreference = old('optimization_preference', 'fastest'))
            @php($selectedDestinationKeys = old(
                'destination_keys',
                session('routeResult')
                    ? array_column(session('routeResult')['stops'], 'route_key')
                    : array_slice(array_column($availablePlaces, 'route_key'), 0, 2)
            ))
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
                        @php($destination = collect($availablePlaces)->firstWhere('route_key', $destinationKey))
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
                        <div class="saved-place-search">
                            <input
                                type="search"
                                data-saved-place-search
                                data-search-url="{{ route('route.saved-places.search') }}"
                                data-collection-id="{{ $collection?->collection_id }}"
                                placeholder="{{ __('route_form.search_placeholder') }}"
                                autocomplete="off"
                            >
                            <small data-saved-search-status>{{ __('route_form.showing_saved_places') }}</small>
                        </div>
                    @endif
                    <select id="add-destination" data-add-destination>
                        <option value="">{{ __('route.choose_place') }}</option>
                        @foreach ($availablePlaces as $destination)
                            <option value="{{ $destination['route_key'] }}" data-name="{{ $destination['name'] }}">
                                {{ $destination['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" data-add-stop>{{ __('route.add_stop') }}</button>
                    @if($usingSavedPlaces)
                        <button
                            type="button"
                            class="saved-place-load-more"
                            data-saved-place-load-more
                            @if($savedPlacesCount <= 20) hidden @endif
                        >{{ __('route_form.load_more') }}</button>
                    @endif
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
<script>
@php($routeTranslations = [
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
])
window.routeTranslations = {{ Illuminate\Support\Js::from($routeTranslations) }};
</script>
<script src="{{ asset('js/route-planning.js') }}?v={{ filemtime(public_path('js/route-planning.js')) }}"></script>
@if (session('routeResult') && $googleMapsBrowserKey && !($isFlightOnlyRoute ?? false))
    <script type="application/json" id="route-map-data">
        @json(session('routeResult'))
    </script>
    <script
        async
        src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsBrowserKey) }}&libraries=geometry&callback=initRouteMap"
    ></script>
@endif
@endpush
