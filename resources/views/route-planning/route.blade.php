@extends('layouts.app')

@section('title', 'Route Planning')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/route-planning.css') }}?v={{ filemtime(public_path('css/route-planning.css')) }}"
>
@endpush

@section('content')
<div class="route-page">
    <p class="route-description">
        Choose your preferred route optimisation option.
    </p>

    @if($usingSavedPlaces)
        <section class="saved-route-summary">
            <span class="saved-places-label">
                <span class="saved-places-icon" aria-hidden="true">&#9825;</span>
                {{ $collection ? $collection->name : 'Your saved places' }}
            </span>
            <h2>{{ $savedPlaces->count() }} destinations ready</h2>
            <div>
                @foreach($savedPlaces->take(8) as $savedPlace)
                    <span>{{ $savedPlace->attraction->attraction_name }}</span>
                @endforeach
            </div>
            @if($savedPlaces->count() > 8)
                <small>The first 8 saved places will be used for this itinerary.</small>
            @elseif($savedPlaces->count() < 2)
                <small>Save at least two places to generate an itinerary.</small>
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
                    {{ ($routeResult['travel_mode'] ?? 'TRANSIT') === 'DRIVE' ? 'Driving route' : 'Public transit route' }}
                </span>
                @if(!empty($routeResult['omitted_places']))
                    <div class="route-omitted-warning">
                        <strong>Not included in this route:</strong>
                        {{ implode(', ', $routeResult['omitted_places']) }}.
                        Google Maps could not connect these places by road or public transit.
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
                        <strong>{{ count(session('routeOptions')) }} Route Options Found</strong>
                        <span>Compare the available {{ ($routeResult['travel_mode'] ?? 'TRANSIT') === 'DRIVE' ? 'driving' : 'public-transport' }} routes</span>
                    </div>
                </div>

                <div class="route-option-grid">
                    @foreach (session('routeOptions') as $optionIndex => $option)
                        <article class="route-option-card {{ $routeResult['option_index'] === $optionIndex ? 'is-selected' : '' }}">
                            <div class="route-option-header">
                                <div>
                                    <span class="route-number">Route {{ $loop->iteration }}</span>
                                    @if ($routeResult['option_index'] === $optionIndex)
                                        <span class="route-badge">Selected</span>
                                    @endif
                                </div>
                                <strong>{{ $option['option_label'] }}</strong>
                            </div>

                            <div class="route-metrics">
                                <div>
                                    <span>Total time</span>
                                    <strong>{{ $option['total_duration_display'] }}</strong>
                                </div>
                                @if (($routeResult['travel_mode'] ?? 'TRANSIT') !== 'DRIVE')
                                    <div>
                                        <span>Total fare</span>
                                        <strong>
                                            {{ $option['total_fare'] !== null
                                                ? $option['fare_currency'] . ' ' . number_format($option['total_fare'], 2)
                                                : 'Unavailable' }}
                                        </strong>
                                    </div>
                                @endif
                                <div>
                                    <span>Distance</span>
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
                                                    ? 'Starting point'
                                                    : number_format($stop['distance_from_previous'], 2) . ' km from previous stop' }}
                                            </small>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>

                            @if ($routeResult['option_index'] === $optionIndex)
                                <button type="button" class="select-route-button selected-route" disabled>
                                    Selected Route
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
                                        Select This Route &rarr;
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
                    aria-label="Interactive map of the {{ ($routeResult['travel_mode'] ?? 'TRANSIT') === 'DRIVE' ? 'driving' : 'public-transport' }} route"
                ></div>
            @else
                <div class="route-map-warning">
                    Add GOOGLE_MAPS_BROWSER_API_KEY to .env to display the interactive map.
                </div>
            @endif

            <div class="trip-overview">
                <div>
                    <span>Start</span>
                    <strong>{{ $routeResult['stops'][0]['name'] }}</strong>
                    <small>{{ $routeResult['departure_time'] }}</small>
                </div>
                <div class="trip-overview-arrow">&rarr;</div>
                <div>
                    <span>Final stop</span>
                    <strong>{{ $routeResult['stops'][count($routeResult['stops']) - 1]['name'] }}</strong>
                    <small>{{ $routeResult['arrival_time'] }}</small>
                </div>
                <div>
                    <span>Total time</span>
                    <strong>{{ $routeResult['total_duration_display'] }}</strong>
                </div>
            </div>

            <div class="transit-legs">
                <h3>
                    {{ ($routeResult['travel_mode'] ?? 'TRANSIT') === 'DRIVE' ? 'Driving trip plan' : 'Public transport trip plan' }}
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
                                            Fare unavailable
                                        @endif
                                    @endif
                                </small>
                            </div>

                            <div class="simple-trip-stop">
                                <time>{{ $leg['arrival_time'] }}</time>
                                <span class="simple-trip-dot"></span>
                                <strong>Arrive at {{ $leg['to'] }}</strong>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="guidance-button"
                            data-guidance-open="guidance-{{ $loop->index }}"
                        >
                            View step-by-step guidance
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
                                <span>Step-by-step guidance</span>
                                <h3 id="guidance-title-{{ $loop->index }}">{{ $leg['from'] }} &rarr; {{ $leg['to'] }}</h3>
                            </div>
                            <button type="button" class="guidance-close" data-guidance-close aria-label="Close guidance"></button>
                        </div>
                        <button type="button" class="guidance-button" data-reward-activity="export_guidance" data-reward-url="{{ route('rewards.activity') }}">Export step-by-step guidance</button>
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
                                            <small>Headsign: {{ $step['headsign'] }}</small>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            @endforeach

            <div class="route-total">
                <span>Total distance</span>
                <strong>{{ number_format($routeResult['total_distance'], 2) }} km</strong>
                <span>Estimated travel time</span>
                <strong>{{ $routeResult['total_duration_display'] }}</strong>
                @if (($routeResult['travel_mode'] ?? 'TRANSIT') !== 'DRIVE')
                    <span>Estimated fare</span>
                    <strong>
                        @if ($routeResult['total_fare'] !== null)
                            {{ $routeResult['fare_currency'] }} {{ number_format($routeResult['total_fare'], 2) }}
                        @else
                            Not available from Google
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
                <h2>Travel Preference</h2>
                <p>Choose how to optimise your route</p>
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
                        <strong>Destinations</strong>
                        <small>Arrange your stops in the order you want to visit them.</small>
                    </div>
                    <span data-destination-count>{{ count($selectedDestinationKeys) }} stops</span>
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
                                    <button type="button" data-move="up" aria-label="Move {{ $destination['name'] }} up">&uarr;</button>
                                    <button type="button" data-move="down" aria-label="Move {{ $destination['name'] }} down">&darr;</button>
                                    <button type="button" data-remove aria-label="Remove {{ $destination['name'] }}">&times;</button>
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ol>

                <div class="itinerary-add">
                    <label for="add-destination">Add destination</label>
                    <select id="add-destination" data-add-destination>
                        <option value="">Choose a place</option>
                        @foreach ($availablePlaces as $destination)
                            <option value="{{ $destination['route_key'] }}" data-name="{{ $destination['name'] }}">
                                {{ $destination['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" data-add-stop>Add stop</button>
                </div>
                <p class="itinerary-hint" data-itinerary-hint>Choose at least two destinations.</p>
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
                        <strong>Fastest Route</strong>
                        <small>Minimise total travel time</small>
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
                        <strong>Shortest Distance</strong>
                        <small>Minimise total distance covered</small>
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
                        <strong>Lowest Cost</strong>
                        <small>Minimise transportation fare</small>
                    </span>
                </label>
            </div>
        </section>

        <button type="submit" class="continue-button" @disabled($usingSavedPlaces && $savedPlaces->count() < 2)>
            Continue
        </button>
    </form>
    </div>
</div>
@endsection

@push('scripts')
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