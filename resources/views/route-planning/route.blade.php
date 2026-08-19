@extends('layouts.app')

@section('title', 'Route Planning')

@push('styles')
<link
    rel="stylesheet"
    href="{{ asset('css/route-planning.css') }}"
>
@endpush

@section('content')
<div class="route-page">
    <p class="route-description">
        Choose your preferred route optimisation option.
    </p>

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

    <div class="route-workspace">
    @if (session('routeResult'))
        @php($routeResult = session('routeResult'))
        <section class="route-result">
            <div class="card-heading">
                <h2>{{ $routeResult['title'] }}</h2>
                <p>{{ $routeResult['description'] }}</p>
            </div>

            @if (session('routeOptions'))
                <div class="route-options-heading">
                    <div>
                        <strong>{{ count(session('routeOptions')) }} Route Options Found</strong>
                        <span>Compare the available public-transport routes</span>
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
                                <div>
                                    <span>Total fare</span>
                                    <strong>
                                        {{ $option['total_fare'] !== null
                                            ? $option['fare_currency'] . ' ' . number_format($option['total_fare'], 2)
                                            : 'Unavailable' }}
                                    </strong>
                                </div>
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
                                    <input type="hidden" name="optimization_preference" value="{{ $routeResult['preference'] }}">
                                    <input type="hidden" name="route_option_index" value="{{ $optionIndex }}">
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
                    aria-label="Interactive map of the public-transport route"
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
                <h3>Public transport trip plan</h3>
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
                                    &middot;
                                    @if ($leg['fare'] !== null)
                                        {{ $leg['fare_currency'] }} {{ number_format($leg['fare'], 2) }}
                                    @else
                                        Fare unavailable
                                    @endif
                                </small>
                            </div>

                            <div class="simple-trip-stop">
                                <time>{{ $leg['arrival_time'] }}</time>
                                <span class="simple-trip-dot"></span>
                                <strong>Arrive at {{ $leg['to'] }}</strong>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
            <div class="route-total">
                <span>Total distance</span>
                <strong>{{ number_format($routeResult['total_distance'], 2) }} km</strong>
                <span>Estimated travel time</span>
                <strong>{{ $routeResult['total_duration_display'] }}</strong>
                <span>Estimated fare</span>
                <strong>
                    @if ($routeResult['total_fare'] !== null)
                        {{ $routeResult['fare_currency'] }} {{ number_format($routeResult['total_fare'], 2) }}
                    @else
                        Not available from Google
                    @endif
                </strong>
            </div>
        </section>
    @endif

    <form
        action="{{ route('route.preference') }}"
        method="POST"
    >
        @csrf

        <section class="preference-card">
            <div class="card-heading">
                <h2>Travel Preference</h2>
                <p>Choose how to optimise your route</p>
            </div>
            <p class="google-attribution">Powered by Google, &copy; {{ date('Y') }} Google</p>

            @php($selectedPreference = old('optimization_preference', 'fastest'))
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

        <button type="submit" class="continue-button">
            Continue
        </button>
    </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/route-planning.js') }}"></script>
@if (session('routeResult') && $googleMapsBrowserKey)
    <script>
        window.routeMapData = @json(session('routeResult'));
    </script>
    <script
        async
        src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsBrowserKey) }}&libraries=geometry&callback=initRouteMap"
    ></script>
@endif
@endpush
