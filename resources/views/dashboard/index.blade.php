<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ExploreMY - Dashboard</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>

@include('components.navbar')

<div class="dashboard-page">
	<div class="container content-area">
		<div class="dashboard-card mb-4">
			<div class="d-flex align-items-center gap-4">
				<div class="profile-avatar-wrapper">
					<img src="{{ Auth::user()->profile_picture }}" class="profile-avatar">
				</div>
				<div>
					<h2 class="profile-name">
						Welcome {{ Auth::user()->name }}
					</h2>
					<p class="text-muted mb-0">
						{{ Auth::user()->email }}
					</p>
				</div>
			</div>
		</div>
	</div>
</div>

@include('components.footer')

</body>
</html>