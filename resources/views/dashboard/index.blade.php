<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ __('misc.dashboard.meta') }}">
    <title>ExploreMY - {{ __('ui.dashboard.title') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
<link rel="stylesheet" href="{{ asset('css/dashboard-home.css') }}?v={{ filemtime(public_path('css/dashboard-home.css')) }}"></head>
<body>
<a class="skip-link" href="#main-content">{{ __('misc.dashboard.skip') }}</a>
@include('components.navbar')

<main id="main-content" class="dashboard-page" tabindex="-1">
    <div class="container content-area">
        @if(session('setup_complete'))<p role="status" class="alert alert-success">{{ __('setup.change_later') }} <a href="{{ route('profile') }}">{{ __('ui.profile.my_profile') }}</a></p>@endif<header class="home-heading">
            <div><span class="home-eyebrow">ExploreMY</span><h1>{{ __('dashboard_home.greeting', ['name' => $user->name]) }}</h1><p>{{ __('dashboard_home.intro') }}</p></div>
            <a class="home-button" href="{{ route('attractions.index') }}">{{ __('dashboard_home.explore') }} <span aria-hidden="true">↗</span></a>
        </header>
        <div class="home-grid">
            <section class="home-trip" aria-labelledby="trip-heading">
                <span class="home-eyebrow">{{ __('dashboard_home.your_trip') }}</span>
                @if($upcomingTrip)
                    <span class="home-badge">{{ __($upcomingTrip->start_date->lte(today()) ? 'dashboard_home.in_progress' : 'dashboard_home.upcoming') }}</span>
                    <h2 id="trip-heading">{{ $upcomingTrip->title }}</h2>
                    <p class="home-dates">{{ $upcomingTrip->start_date->translatedFormat('d M Y') }}@if($upcomingTrip->end_date) — {{ $upcomingTrip->end_date->translatedFormat('d M Y') }}@endif</p>
                    <p>{{ __('dashboard_home.trip_help') }}</p>
                    <a class="home-button home-button-light" href="{{ route('itineraries.show', $upcomingTrip) }}">{{ __('itinerary.view') }} →</a>
                @else
                    <h2 id="trip-heading">{{ __('dashboard_home.empty_trip') }}</h2>
                    <p>{{ __('dashboard_home.empty_trip_help') }}</p>
                    <a class="home-button home-button-light" href="{{ route('saved-places.index') }}">{{ __('dashboard_home.start') }} →</a>
                @endif
                <a class="home-secondary" href="{{ route('itineraries.index') }}">{{ __('dashboard_home.all_trips') }} →</a>
            </section>
            <section class="home-planning" aria-labelledby="planning-heading">
                <span class="home-eyebrow">{{ __('dashboard_home.continue') }}</span>
                <h2 id="planning-heading">{{ $collection ? $collection->name : __('dashboard_home.empty_collection') }}</h2>
                @if($collection)
                    <p class="home-count">{{ __('route.stops', ['count' => $collection->items_count]) }}</p>
                    <p>{{ __($collection->items_count >= 2 ? 'dashboard_home.ready' : 'dashboard_simple.need_places') }}</p>
                    @if($collection->items_count >= 2)
                        <a class="home-button" href="{{ route('route.index', ['source' => 'saved', 'collection' => $collection->collection_id]) }}">{{ __('saved_extra.generate') }} →</a>
                    @else
                        <a class="home-button" href="{{ route('saved-places.index') }}#collection-{{ $collection->collection_id }}">{{ __('dashboard_home.add_places') }} →</a>
                    @endif
                    <a class="home-secondary" href="{{ route('saved-places.index') }}#collection-{{ $collection->collection_id }}">{{ __('dashboard_simple.edit_collection') }}</a>
                @else
                    <p>{{ __('dashboard_simple.no_collection') }}</p>
                    <a class="home-button" href="{{ route('saved-places.index') }}">{{ __('saved.create_heading') }} →</a>
                @endif
            </section>
        </div>
        <nav class="home-shortcuts" aria-label="{{ __('dashboard_home.shortcuts') }}">
            <a href="{{ route('saved-places.index') }}"><span class="home-symbol" aria-hidden="true">♡</span><span><strong>{{ __('ui.dashboard.saved') }}</strong><small>{{ __('dashboard_home.saved_help') }}</small></span><b aria-hidden="true">→</b></a>
            <a href="{{ route('transportation') }}"><span class="home-symbol" aria-hidden="true">↔</span><span><strong>{{ __('ui.nav.transportation') }}</strong><small>{{ __('dashboard_home.transport_help') }}</small></span><b aria-hidden="true">→</b></a>
            <a href="{{ route('rewards') }}"><span class="home-symbol" aria-hidden="true">✦</span><span><strong>{{ __('pages.common.rewards') }}</strong><small>{{ __('dashboard_home.rewards_help') }}</small></span><b aria-hidden="true">→</b></a>
        </nav>
    </div>
</main>
@include('components.footer')
</body>
</html>
