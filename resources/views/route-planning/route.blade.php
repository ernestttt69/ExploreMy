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
            <h2>{{ __('route.destinations_ready', ['count' => $savedPlaces->count()]) }}</h2>
            <div>
                @foreach($savedPlaces->take(8) as $savedPlace)
                    <span>{{ $savedPlace->attraction->attraction_name }}</span>
                @endforeach
            </div>
            @if($savedPlaces->count() > 8)
                <small>{{ __('route.first_eight') }}</small>
            @elseif($savedPlaces->count() < 2)
                <small>{{ __('route.need_two') }}</small>
            @endif
        </section>
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
        <section class="route-result">
            <div class="card-heading">
                <h2>{{ $routeResult['title'] }}</h2>
                <p>{{ $routeResult['description'] }}</p>
                <span class="route-mode-badge">
                    {{ ($routeResult['travel_mode'] ?? 'TRANSIT') === 'DRIVE' ? __('route.driving_route') : __('route.transit_route') }}
                </span>
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

            @if (session('routeOptions'))
                <div class="route-options-heading">
                    <div>
                        <strong>{{ __('route.options_found', ['count' => count(session('routeOptions'))]) }}</strong>
                        <span>{{ __('route.compare', ['mode' => ($routeResult['travel_mode'] ?? 'TRANSIT') === 'DRIVE' ? __('route.driving') : __('route.public_transport')]) }}</span>
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
                                @if (($routeResult['travel_mode'] ?? 'TRANSIT') !== 'DRIVE')
                                    <div>
                                        <span>{{ __('route.total_fare') }}</span>
                                        <strong>
                                            {{ $option['total_fare'] !== null
                                                ? $option['fare_currency'] . ' ' . number_format($option['total_fare'], 2)
                                                : __('route.unavailable') }}
                                        </strong>
                                    </div>
                                @endif
                                <div>
                                    <span>{{ __('route.distance') }}</span>
                                    <strong>{{ number_format($option['total_distance'], 2) }} km</strong>
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

            @if ($googleMapsBrowserKey)
                <div
                    id="route-map"
                    class="route-map"
                    role="img"
                    aria-label="{{ __('route.map_aria', ['mode' => ($routeResult['travel_mode'] ?? 'TRANSIT') === 'DRIVE' ? __('route.driving') : __('route.public_transport')]) }}"
                ></div>
            @else
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
                    {{ ($routeResult['travel_mode'] ?? 'TRANSIT') === 'DRIVE' ? __('route.driving_plan') : __('route.transit_plan') }}
                </h3>
                @foreach ($routeResult['transit_legs'] as $leg)
                    <section class="transit-leg">
                        <div class="transit-leg-heading">
                            <div>
                                <strong>{{ $leg['from'] }} &rarr; {{ $leg['to'] }}</strong>
                                <span>{{ $leg['departure_time'] }}–{{ $leg['arrival_time'] }}</span>
                            </div>
                            <strong>{{ $leg['duration_display'] }}</strong>
                        </div>

                        <div class="simple-trip-timeline">
                            <div class="simple-trip-stop">
                                <time>{{ $leg['departure_time'] }}</time>
                                <span class="simple-trip-dot"></span>
                                <strong>{{ $leg['from'] }}</strong>
                            </div>

                            <div class="simple-trip-transport">
                                <span>{{ $leg['transport_summary'] }}</span>
                                <small>
                                    {{ $leg['duration_display'] }}
                                    &middot; {{ number_format($leg['distance'], 2) }} km
                                    @if (($routeResult['travel_mode'] ?? 'TRANSIT') !== 'DRIVE')
                                        &middot;
                                        @if ($leg['fare'] !== null)
                                            {{ $leg['fare_currency'] }} {{ number_format($leg['fare'], 2) }}
                                        @else
                                            {{ __('route.fare_unavailable') }}
                                        @endif
                                    @endif
                                </small>
                            </div>

                            <div class="simple-trip-stop">
                                <time>{{ $leg['arrival_time'] }}</time>
                                <span class="simple-trip-dot"></span>
                                <strong>{{ __('route.arrive', ['place' => $leg['to']]) }}</strong>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="guidance-button"
                            data-guidance-open="guidance-{{ $loop->index }}"
                        >
                            {{ __('route.view_guidance') }}
                        </button>
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
                        <button type="button" class="guidance-button" data-reward-activity="export_guidance" data-reward-url="{{ route('rewards.activity') }}">{{ __('route.export_guidance') }}</button>
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
                <span>{{ __('route.total_distance') }}</span>
                <strong>{{ number_format($routeResult['total_distance'], 2) }} km</strong>
                <span>{{ __('route.estimated_time') }}</span>
                <strong>{{ $routeResult['total_duration_display'] }}</strong>
                @if (($routeResult['travel_mode'] ?? 'TRANSIT') !== 'DRIVE')
                    <span>{{ __('route.estimated_fare') }}</span>
                    <strong>
                        @if ($routeResult['total_fare'] !== null)
                            {{ $routeResult['fare_currency'] }} {{ number_format($routeResult['total_fare'], 2) }}
                        @else
                            {{ __('route.not_available_google') }}
                        @endif
                    </strong>
                @endif
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
            <p class="google-attribution">Powered by Google, &copy; {{ date('Y') }} Google</p>

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
                    <select id="add-destination" data-add-destination>
                        <option value="">{{ __('route.choose_place') }}</option>
                        @foreach ($availablePlaces as $destination)
                            <option value="{{ $destination['route_key'] }}" data-name="{{ $destination['name'] }}">
                                {{ $destination['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" data-add-stop>{{ __('route.add_stop') }}</button>
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

        <button type="submit" class="continue-button" @disabled($usingSavedPlaces && $savedPlaces->count() < 2)>
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
    'shareText' => __('route.share_text'),
])
window.routeTranslations = {{ Illuminate\Support\Js::from($routeTranslations) }};
</script>
<script src="{{ asset('js/route-planning.js') }}?v={{ filemtime(public_path('js/route-planning.js')) }}"></script>
@if (session('routeResult') && $googleMapsBrowserKey)
    <script type="application/json" id="route-map-data">
        {!! Js::from(session('routeResult')) !!}
    </script>
    <script
        async
        src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsBrowserKey) }}&libraries=geometry&callback=initRouteMap"
    ></script>
@endif
@endpush