<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{{ $trip->title }} — ExploreMY</title>

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="{{ asset('css/itineraries.css') }}" rel="stylesheet">
</head>
<body>

	@include('partials.navbar')

	<main class="container py-4">

		@if (session('status'))
			<div class="alert alert-success">{{ session('status') }}</div>
		@endif

		<a href="{{ route('trips.index') }}" class="back-link">&larr; Back to My Trips</a>

		<div class="itinerary-detail-card mt-3">
			<div class="trip-icon">🌍</div>
			<h1>{{ $trip->title }}</h1>
			<p class="trip-meta">
				{{ $trip->start_date->format('M d') }} - {{ $trip->end_date->format('M d, Y') }}
				&bull; {{ $trip->days }} Days
			</p>
			<div class="co2-badge">
				Estimated: {{ $trip->co2_kg }}kg CO&#8322;
			</div>
		</div>

	</main>

	@include('partials.footer')

</body>
</html>
