<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ExploreMY - Login</title>

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="{{ asset('css/login.css') }}">

	<script src="https://accounts.google.com/gsi/client" async defer></script>
</head>

<body>

<div class="login-wrapper">
	<div class="card login-card text-center">

		<div class="mb-4">
			<div class="logo-container mx-auto mb-3">
				<img src="{{ asset('images/ExploreMy_icon.jpeg') }}" alt="ExploreMY Logo" class="img-fluid">
			</div>

			<h1 class="brand-title">ExploreMY</h1>

			<p class="brand-tagline">
				Explore Smarter, Travel Better.
			</p>
		</div>

		<div class="mb-4">
			<h5 class="welcome-title">
				Welcome back
			</h5>

			<p class="welcome-text">
				Sign in with Google to start planning your Malaysian journey.
			</p>
		</div>

		<div class="d-flex justify-content-center">
			<div id="g_id_onload"
				data-client_id="YOUR_GOOGLE_CLIENT_ID"
				data-callback="handleCredentialResponse"
				data-auto_prompt="false">
			</div>

			<div class="g_id_signin"
				data-type="standard"
				data-shape="pill"
				data-theme="outline"
				data-text="continue_with"
				data-size="large">
			</div>
		</div>

		<div class="mt-3">
			<small>
				🔒 Protected by Google Identity Services
			</small>
		</div>

	</div>
</div>

<script>
function handleCredentialResponse(response)
{
	fetch('/google-login', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': '{{ csrf_token() }}'
		},
		body: JSON.stringify({
			credential: response.credential
		})
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			window.location.href = "/dashboard";
		} else {
			alert(data.message);
		}
	})
	.catch(error => {
		console.error(error);
		alert("Login failed");
	});
}
</script>

</body>
</html>