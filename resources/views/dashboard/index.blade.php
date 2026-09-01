<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ __('misc.dashboard.meta') }}">
    <title>ExploreMY - {{ __('ui.dashboard.title') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
</head>
<body>
<a class="skip-link" href="#main-content">{{ __('misc.dashboard.skip') }}</a>
@include('components.navbar')

<main id="main-content" class="dashboard-page" tabindex="-1">
    <div class="container content-area">
        <section class="welcome-panel" aria-labelledby="welcome-title">
            <div class="welcome-content">
                <span class="eyebrow">{{ __('ui.dashboard.eyebrow') }}</span>
                <h1 id="welcome-title">{{ __('ui.messages.welcome') }}</h1>
                <p>{{ __('ui.dashboard.intro') }}</p>
                <div class="welcome-actions">
                    <a href="{{ route('attractions.index') }}" class="btn-dashboard btn-dashboard-light">{{ __('ui.dashboard.explore_my') }} <span aria-hidden="true">&rarr;</span></a>
                </div>
            </div>
            <div class="welcome-visual" aria-hidden="true"><div class="route-line"></div><i class="pin pin-one"></i><i class="pin pin-two"></i><div class="destination-card"><span>MY</span><div><small>{{ __('ui.dashboard.next') }}</small><strong>{{ __('ui.dashboard.explore_my') }}</strong></div></div></div>
        </section>

        <section class="dashboard-section" aria-labelledby="overview-title">
            <div class="section-heading"><div><span class="section-kicker">{{ __('ui.dashboard.glance') }}</span><h2 id="overview-title">{{ __('ui.dashboard.travel_dashboard') }}</h2></div><time class="today-label" datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, d M Y') }}</time></div>
            <div class="stats-grid">
                <a href="{{ route('transportation') }}" class="stat-card"><span class="stat-icon icon-blue" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><rect x="5" y="3" width="14" height="15" rx="3"/><path d="M8 7h8M8 13h.01M16 13h.01M8 18l-2 3M16 18l2 3"/></svg></span><span><strong>{{ __('ui.nav.transportation') }}</strong><small>{{ __('pages.shortcuts.transport') }}</small></span><b aria-hidden="true">&rsaquo;</b></a>
                <a href="{{ route('trips.index') }}" class="stat-card"><span class="stat-icon icon-sage" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M4 7h16v13H4zM9 7V4h6v3M4 12h16M10 12v2h4v-2"/></svg></span><span><strong>{{ __('pages.common.trips') }}</strong><small>{{ __('pages.shortcuts.trips') }}</small></span><b aria-hidden="true">&rsaquo;</b></a>
                <a href="{{ route('rewards') }}" class="stat-card"><span class="stat-icon icon-amber" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/></svg></span><span><strong>{{ __('pages.common.rewards') }}</strong><small>{{ __('pages.shortcuts.rewards') }}</small></span><b aria-hidden="true">&rsaquo;</b></a>
            </div>
        </section>

        <section class="dashboard-section personalisation-panel" aria-labelledby="interests-title">
            <div class="section-heading"><div><span class="section-kicker">{{ __('ui.dashboard.made_for') }}</span><h2 id="interests-title">{{ __('ui.dashboard.your_interests') }}</h2></div></div>
            @if(!$user->personalisation_consent)
                <div class="preference-message disabled" role="status">{{ __('ui.dashboard.disabled') }} <a href="{{ route('profile') }}#privacy">{{ __('ui.profile.privacy') }} <span aria-hidden="true">&rarr;</span></a></div>
            @elseif($preferences->isEmpty())
                <div class="preference-message" role="status">{{ __('ui.dashboard.no_interests') }} <a href="{{ route('travel-preferences.edit') }}">{{ __('ui.dashboard.update') }} <span aria-hidden="true">&rarr;</span></a></div>
            @else
                <p class="preference-intro">{{ __('ui.dashboard.interest_intro') }}</p>
                <ul class="interest-chips" aria-label="{{ __('misc.dashboard.interests') }}">@foreach($preferences as $preference)<li>{{ $preference->localized_name }}</li>@endforeach</ul>
            @endif
            <div class="travel-notes-card"><span>{{ __('ui.dashboard.travel_notes') }}</span><p>{{ filled($user->bio) ? $user->bio : __('ui.dashboard.no_notes') }}</p>@if(blank($user->bio))<a href="{{ route('profile') }}#personal">{{ __('ui.profile.travel_notes') }} <span aria-hidden="true">&rarr;</span></a>@endif</div>
        </section>

    </div>
</main>
@include('components.footer')
</body>
</html>
