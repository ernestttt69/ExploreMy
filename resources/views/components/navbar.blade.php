@php
	$pendingRewardActivities = session('pending_reward_activities', []);
	$hasPendingRewards = collect($pendingRewardActivities)->sum() > 0;
@endphp

<header class="header-wrapper fixed-top">
	<div class="container">

		<div class="top-header-bar">

			<div class="d-flex justify-content-between align-items-center">

				<a href="{{ route('explore') }}" class="nav-brand-container text-decoration-none" aria-label="{{ __('ui.nav.explore') }}">

					<div class="nav-logo-badge">
						<img src="{{ asset('images/ExploreMy_icon.jpeg') }}">
					</div>

					<h2 class="nav-brand-title">
						ExploreMY
					</h2>

				</a>

				@auth
				<div class="d-flex align-items-center gap-3">

					<a
						href="{{ route('saved-places.index') }}"
						class="nav-saved-places {{ request()->routeIs('saved-places.*') ? 'active' : '' }}"
						title="{{ __('ui.footer.saved') }}"
						aria-label="{{ __('ui.footer.saved') }}"
					>
						<span aria-hidden="true">&#9825;</span>
					</a>

					<a href="{{ route('profile') }}" class="nav-user-pill text-decoration-none text-dark">

						<img src="{{ Auth::user()->profile_picture }}" class="nav-avatar">
						<span>
							{{ Auth::user()->name }}
						</span>

					</a>

					<form method="POST" action="{{ route('logout') }}">
						@csrf

						<button class="btn btn-logout">
							{{ __('ui.nav.logout') }}
						</button>

					</form>

				</div>
				@else
				<div class="d-flex align-items-center gap-3">
					<a href="{{ route('login') }}" class="btn btn-logout text-decoration-none">{{ __('ui.nav.login') }}</a>
				</div>
				@endauth
			</div>

		</div>

		<div class="sub-navbar">

			<ul class="nav-menu">

				<li>
					<a href="{{ route('dashboard') }}" class="nav-link-custom {{ request()->routeIs('dashboard') ? 'active' : '' }}">
						{{ __('ui.nav.dashboard') }}
					</a>
				</li>

				<li>
					<a href="{{ route('explore') }}" class="nav-link-custom {{ request()->routeIs('explore', 'attractions.*') ? 'active' : '' }}">
						{{ __('ui.nav.explore') }}
					</a>
				</li>

				<li>
					<a href="{{ route('transportation') }}" class="nav-link-custom {{ request()->routeIs('transportation', 'transport.*') ? 'active' : '' }}">
						{{ __('ui.nav.transportation') }}
					</a>
				</li>

				<li>
					<a href="{{ route('itineraries.index') }}" class="nav-link-custom {{ request()->routeIs('itineraries.*') ? 'active' : '' }}">
						{{ __('pages.common.trips') }}
					</a>
				</li>

				<li>
					<a href="{{ route('rewards') }}" class="nav-link-custom rewards-nav-link {{ request()->routeIs('rewards') ? 'active' : '' }}">
						{{ __('pages.common.rewards') }}
						@if($hasPendingRewards)
							<span class="nav-reward-dot" aria-label="{{ __('rewards.collect') }}"></span>
						@endif
					</a>
				</li>

				<li>
					<a href="{{ route('about-malaysia') }}" class="nav-link-custom {{ request()->routeIs('about-malaysia') ? 'active' : '' }}">
						{{ __('ui.nav.about') }}
					</a>
				</li>


			</ul>

		</div>

	</div>
</header>
<script src="{{ asset('js/site-interactions.js') }}" defer></script>
