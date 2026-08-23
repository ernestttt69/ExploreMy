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

		<div class="row">

			<div class="col-md-6">
				<div class="dashboard-card">
					<h3>Public Transportation</h3>

					<p>
						Explore MRT, LRT, bus and other transportation options.
					</p>

					<button class="btn btn-success">
						View Transportation
					</button>
				</div>
			</div>

			<div class="col-md-6">
				<div class="dashboard-card">
					<h3>Start Your Plan Now</h3>

					<p>
						Create your travel plan and discover places around Malaysia.
					</p>

					<button class="btn btn-outline-success">
						Start Planning
					</button>
				</div>
			</div>

		</div>

	</div>
</div>

@include('components.footer')

</body>
</html>