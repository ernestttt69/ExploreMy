<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Planned Trips — ExploreMY</title>

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="{{ asset('css/trips.css') }}" rel="stylesheet">
</head>
<body>

	@include('components.navbar')

	<main class="container py-4">

		<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 trips-page-header mb-4">
			<h1 class="mb-0">My Planned Trips</h1>
			<a href="{{ route('itineraries.create') }}" class="btn btn-create-itinerary">
				+ Create New Itinerary
			</a>
		</div>

		<div class="row g-4">
			@forelse ($trips as $trip)
				<div class="col-12 col-md-6 col-lg-4">
					<div class="trip-card">
						<div class="trip-icon">🌍</div>
						<h2>{{ $trip->title }}</h2>
						<p class="trip-meta">
							{{ $trip->start_date->format('M d') }} - {{ $trip->end_date->format('M d, Y') }}
							&bull; {{ $trip->days }} Days
						</p>
						<div class="co2-badge">
							Estimated: {{ $trip->co2_kg }}kg CO&#8322;
						</div>
						<div>
							<a href="{{ route('itineraries.show', $trip->id) }}" class="btn btn-view-itinerary">
								View Itinerary &rarr;
							</a>
						</div>
					</div>
				</div>
			@empty
				<div class="col-12">
					<p class="text-center text-muted">
						You haven't planned any trips yet.
					</p>
				</div>
			@endforelse
		</div>

	</main>

	@include('components.footer')

</body>
</html>