<!DOCTYPE html>
<html>
<head>
	<title>Profile</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
	<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
</head>
<body>

@include('components.navbar')

<div class="container profile-page">

	<div class="profile-card">

		@if(session('success'))

			<script>
				alert("{{ session('success') }}");
			</script>

		@endif

		<div class="profile-header">

			<form method="POST" action="/profile/update" enctype="multipart/form-data">

				@csrf

				<div class="profile-avatar-wrapper cursor-pointer" onclick="document.getElementById('profile_picture').click()">

					<img 
						src="{{ Auth::user()->profile_picture }}" 
						class="profile-avatar"
						id="avatarPreview"
					>

				</div>

				<input 
					type="file"
					name="profile_picture"
					id="profile_picture"
					accept="image/*"
					hidden
					onchange="previewImage(event)"
				>


				<h2 class="profile-name">
					{{ Auth::user()->name }}
				</h2>

				<p class="profile-email">
					{{ Auth::user()->email }}
				</p>

				<!-- Update Profile -->
				<div class="profile-box">

					<h4 class="section-title">
						Update Profile
					</h4>

					<div class="mb-3">
						<label class="form-label fw-semibold">
							Name
						</label>

						<input
							type="text"
							name="name"
							class="form-control custom-name-input"
							value="{{ Auth::user()->name }}"
						>
					</div>

					<button class="btn btn-profile-primary">
						Save Changes
					</button>

				</div>

				<!-- Travel Preference -->
				<div class="profile-box">

					<h4 class="section-title">
						Travel Preference
					</h4>

					<a
						href="{{ route('travel-preferences.edit') }}"
						class="btn btn-profile-secondary"
					>
						Edit Travel Preference
					</a>

				</div>

				<!-- Saved Place -->
				<div class="profile-box">
					<h4 class="section-title">
						Saved Place
					</h4>

					<a href="{{ route('saved-places.index') }}" class="btn btn-profile-secondary">
						♡ View Saved Places
					</a>
				</div>
			</form>
		</div>
	</div>
</div>


<script>

function previewImage(event)
{
	const image = document.getElementById('avatarPreview');

	image.src = URL.createObjectURL(event.target.files[0]);
}

</script>

</body>
</html>
