<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ExploreMY - My Trips</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="{{ asset('css/trips.css') }}">
</head>
<body>

@include('components.navbar')

<div class="trips-page">
	<div class="container content-area">

		<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 trips-page-header mb-4">
			<h1 class="mb-0">My Planned Trips</h1>
			<a href="#" class="btn btn-create-itinerary">
				+ Create New Itinerary
			</a>
		</div>

		@php
			// Temporary sample data — remove once real trips come from the database.
			$trips = [
				(object) [
					'title'      => 'Penang Eco-Tour',
					'date_range' => 'Aug 12 - Aug 15, 2026',
					'days'       => 4,
					'co2_kg'     => 45,
				],
				(object) [
					'title'      => 'Langkawi Retreat',
					'date_range' => 'Sep 05 - Sep 10, 2026',
					'days'       => 6,
					'co2_kg'     => 120,
				],
			];
		@endphp

		<div class="row g-4">
			@forelse ($trips as $trip)
				<div class="col-12 col-md-6 col-lg-4">
					<div class="trip-card">
						<div class="trip-icon">🌍</div>
						<h2>{{ $trip->title }}</h2>
						<p class="trip-meta">
							{{ $trip->date_range }} &bull; {{ $trip->days }} Days
						</p>
						<div class="co2-badge">
							Estimated: {{ $trip->co2_kg }}kg CO&#8322;
						</div>
						<div>
							<a href="#" class="btn btn-view-itinerary">
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

	</div>
</div>

@include('components.footer')

</body>
</html>