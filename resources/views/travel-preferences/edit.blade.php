<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Travel Preferences</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
</head>

<body>

@include('components.navbar')

<div class="container profile-page">

    <div class="profile-card">

        <div class="profile-header">

            <div class="profile-box">

                <h2 class="section-title mb-4">
                    Travel Preferences
                </h2>

                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                <form
                    action="{{ route('travel-preferences.update') }}"
                    method="POST"
                >
                    @csrf

                    <div class="row">

                        @foreach($categories as $category)

                            <div class="col-md-4 col-sm-6 mb-3">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="preferences[]"
                                        value="{{ $category->preference_id }}"
                                        id="preference-{{ $category->preference_id }}"
                                        {{ in_array(
                                            $category->preference_id,
                                            $selectedPreferences
                                        ) ? 'checked' : '' }}
                                    >

                                    <label
                                        class="form-check-label"
                                        for="preference-{{ $category->preference_id }}"
                                    >
                                        {{ $category->category_name }}
                                    </label>

                                </div>

                            </div>

                        @endforeach

                    </div>

                    <div class="mt-4">

                        <button
                            type="submit"
                            class="btn btn-profile-primary"
                        >
                            Save Preferences
                        </button>

                        <a
                            href="/profile"
                            class="btn btn-profile-secondary ms-2"
                        >
                            Back to Profile
                        </a>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

@include('components.footer')

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>