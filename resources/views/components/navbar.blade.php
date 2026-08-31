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

				@auth
				<div class="d-flex align-items-center gap-3">
					<a href="/profile" class="nav-user-pill text-decoration-none">
						@if(Auth::user()->profile_picture)
							<img src="{{ Auth::user()->profile_picture }}" class="nav-avatar" alt="{{ Auth::user()->name }}">
						@else
							<span class="nav-avatar nav-avatar-fallback">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
						@endif
						<span>
							{{ Auth::user()->name }}
						</span>
					</a>

					<form method="POST" action="/logout">
						@csrf
						<button class="btn btn-logout">
							Logout
						</button>
					</form>
				</div>
				@endauth
			</div>
		</div>

		<div class="sub-navbar">
			<ul class="nav-menu">
				<li>
					<a href="/dashboard" class="nav-link-custom {{ request()->is('dashboard') ? 'active' : '' }}">
						Dashboard
					</a>
				</li>
				<li>
					<a href="#" class="nav-link-custom">
						Explore
					</a>
				</li>
				<li>
					<a href="{{ route('itineraries.index') }}" class="nav-link-custom {{ request()->is('trips*') ? 'active' : '' }}">
						My Trips
					</a>
				</li>
			</ul>
		</div>
	</div>
</header>
