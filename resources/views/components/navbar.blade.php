<header class="header-wrapper fixed-top">
	<div class="container">

		<div class="top-header-bar">

			<div class="d-flex justify-content-between align-items-center">

				<a href="{{ route('explore') }}" class="nav-brand-container text-decoration-none" aria-label="Explore Malaysia">

					<div class="nav-logo-badge">
						<img src="{{ asset('images/ExploreMy_icon.jpeg') }}">
					</div>

					<h2 class="nav-brand-title">
						ExploreMY
					</h2>

				</a>

				<div class="d-flex align-items-center gap-3">

					@auth
					<a href="{{ route('profile') }}" class="nav-user-pill text-decoration-none text-dark">

						<img src="{{ Auth::user()->profile_picture }}" class="nav-avatar">

						<span>
							{{ Auth::user()->name }}
						</span>

					</a>

					<a href="{{ route('logout') }}" class="btn btn-logout text-decoration-none">
							{{ __('ui.nav.logout') }}
					</a>
					@else
					<a href="{{ route('login') }}" class="btn btn-logout text-decoration-none">Login</a>
					@endauth

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
					<a href="{{ route('explore') }}" class="nav-link-custom {{ request()->routeIs('explore') ? 'active' : '' }}">
						{{ __('ui.nav.explore') }}
					</a>
				</li>

				<li>
					<a href="{{ route('transportation') }}" class="nav-link-custom {{ request()->routeIs('transportation', 'transport.*') ? 'active' : '' }}">
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
