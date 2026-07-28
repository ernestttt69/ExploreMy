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
					<a href="/profile" class="nav-user-pill text-decoration-none">
						<img src="{{ Auth::user()->profile_picture }}" class="nav-avatar">
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
			</div>
		</div>

		<div class="sub-navbar">
			<ul class="nav-menu">
				<li>
					<a href="/dashboard" class="nav-link-custom active">
						Dashboard
					</a>
				</li>
				<li>
					<a href="#" class="nav-link-custom">
						Explore
					</a>
				</li>
				<li>
					<a href="#" class="nav-link-custom">
						My Trips
					</a>
				</li>
			</ul>
		</div>
	</div>
</header>