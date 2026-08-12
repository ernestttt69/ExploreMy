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
            Please select a travel preference.
        </div>
    @endif

    @if (session('shortestRoute'))
        @php($shortestRoute = session('shortestRoute'))
        <section class="route-result">
            <div class="card-heading">
                <h2>Shortest Malaysian Route</h2>
                <p>Exact route from Kuala Lumpur using straight-line distances</p>
            </div>
            <ol class="route-stops">
                @foreach ($shortestRoute['stops'] as $stop)
                    <li>
                        <strong>{{ $stop['name'] }}</strong>
                        <span>{{ $loop->first ? 'Starting point' : '+' . number_format($stop['distance_from_previous'], 2) . ' km' }}</span>
                    </li>
                @endforeach
            </ol>
            <div class="route-total">
                Total shortest distance:
                <strong>{{ number_format($shortestRoute['total_distance'], 2) }} km</strong>
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

            <div class="preference-list">
                <label class="preference-option selected">
                    <input
                        type="radio"
                        name="optimization_preference"
                        value="fastest"
                        checked
                    >

                    <span class="custom-radio"></span>

                    <span class="preference-icon">⚡</span>

                    <span class="preference-text">
                        <strong>Fastest Route</strong>
                        <small>Minimise total travel time</small>
                    </span>
                </label>

                <label class="preference-option">
                    <input
                        type="radio"
                        name="optimization_preference"
                        value="shortest"
                    >

                    <span class="custom-radio"></span>

                    <span class="preference-icon">📏</span>

                    <span class="preference-text">
                        <strong>Shortest Distance</strong>
                        <small>Minimise total distance covered</small>
                    </span>
                </label>

                <label class="preference-option">
                    <input
                        type="radio"
                        name="optimization_preference"
                        value="eco"
                    >

                    <span class="custom-radio"></span>

                    <span class="preference-icon">🌱</span>

                    <span class="preference-text">
                        <strong>Eco-Friendly</strong>
                        <small>Prioritise public transportation</small>
                    </span>
                </label>

                <label class="preference-option">
                    <input
                        type="radio"
                        name="optimization_preference"
                        value="lowest_cost"
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
@endsection

@push('scripts')
<script src="{{ asset('js/route-planning.js') }}"></script>
@endpush
