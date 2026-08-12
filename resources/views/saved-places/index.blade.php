<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Places</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/saved-places.css') }}">
</head>
<body>

@include('components.navbar')

<main class="container saved-places-page">
    <div class="saved-places-heading">
        <div>
            <p class="page-kicker">Your travel collection</p>
            <h1>Saved Places</h1>
            <p class="page-description">Places you saved for your next Malaysian adventure.</p>
        </div>

        <a href="{{ url('/profile') }}" class="btn-back">← Back to Profile</a>
    </div>

    @if(count($savedPlaces) > 0)
        <div class="row g-4">
            @foreach($savedPlaces as $place)
                <div class="col-12 col-md-6 col-lg-4">
                    <article class="place-card">
                        <img src="{{ $place['image'] }}" alt="{{ $place['name'] }}" class="place-image">

                        <div class="place-content">
                            <div class="place-card-top">
                                <span class="place-category">{{ $place['category'] }}</span>
                                <span class="saved-heart" aria-label="Saved place">♥</span>
                            </div>

                            <h2>{{ $place['name'] }}</h2>
                            <p class="place-location">📍 {{ $place['location'] }}</p>
                            <p class="place-description">{{ $place['description'] }}</p>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <div class="empty-heart">♡</div>
            <h2>No saved places yet</h2>
            <p>Your saved destinations will appear here.</p>
        </div>
    @endif
</main>

</body>
</html>
