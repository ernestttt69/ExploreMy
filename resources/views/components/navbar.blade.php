<header class="header-wrapper fixed-top">
	<div class="container">

		<div class="top-header-bar">

			<div class="d-flex justify-content-between align-items-center">

				<div class="nav-brand-container">

					<div class="nav-logo-badge">
						<img src="{{ asset('images/ExploreMy_icon.jpeg') }}">
					</div>

					<h2 class="nav-brand-title">
						ExploreMY
					</h2>

				</div>

				<div class="d-flex align-items-center gap-3">

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
					<a href="{{ route('attractions.index') }}" class="nav-link-custom {{ request()->routeIs('attractions.*') ? 'active' : '' }}">
						{{ __('ui.nav.explore') }}
					</a>
				</li>

				<li>
					<a href="{{ route('transportation') }}" class="nav-link-custom {{ request()->routeIs('transportation') ? 'active' : '' }}">
						{{ __('ui.nav.transportation') }}
					</a>
				</li>

				<li>
					<a href="{{ route('trips.index') }}" class="nav-link-custom {{ request()->routeIs('trips.*') ? 'active' : '' }}">
						My Trips
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
