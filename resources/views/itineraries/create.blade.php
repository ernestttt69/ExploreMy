<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Create Itinerary — ExploreMY</title>

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="{{ asset('css/itineraries.css') }}" rel="stylesheet">
</head>
<body>

	@include('components.navbar')

	<main class="container py-4">

		<div class="itinerary-form-header mb-4">
			<h1>Create New Itinerary</h1>
		</div>

		@if ($errors->any())
			<div class="alert alert-danger">
				<ul class="mb-0">
					@foreach ($errors->all() as $error)
						<li>{{ $error }}</li>
					@endforeach
				</ul>
			</div>
		@endif

		<form method="POST" action="{{ route('itineraries.store') }}" class="itinerary-form">
			@csrf

			<div class="mb-3">
				<label for="title" class="form-label">Trip title</label>
				<input type="text" name="title" id="title" class="form-control"
					value="{{ old('title') }}" placeholder="e.g. Penang Eco-Tour" required>
			</div>

			<div class="row">
				<div class="col-md-6 mb-3">
					<label for="start_date" class="form-label">Start date</label>
					<input type="date" name="start_date" id="start_date" class="form-control"
						value="{{ old('start_date') }}" required>
				</div>
				<div class="col-md-6 mb-3">
					<label for="end_date" class="form-label">End date</label>
					<input type="date" name="end_date" id="end_date" class="form-control"
						value="{{ old('end_date') }}" required>
				</div>
			</div>

			<div class="mb-4">
				<label for="co2_kg" class="form-label">Estimated CO&#8322; (kg)</label>
				<input type="number" name="co2_kg" id="co2_kg" class="form-control"
					value="{{ old('co2_kg') }}" min="0" placeholder="e.g. 45" required>
			</div>

			<div class="d-flex gap-2">
				<button type="submit" class="btn btn-create-itinerary">
					Save Itinerary
				</button>
				<a href="{{ route('trips.index') }}" class="btn btn-cancel">
					Cancel
				</a>
			</div>
		</form>

	</main>

	@include('components.footer')

</body>
</html>
