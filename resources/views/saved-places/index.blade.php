<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Saved Places</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/dashboard.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/saved-places.css') }}"
    >
</head>

<body>

@include('components.navbar')

<main class="container saved-places-page">

    <div class="saved-places-heading">

        <div>

            <p class="page-kicker">
                Your travel collection
            </p>

            <h1>
                Saved Places
            </h1>

            <p class="page-description">
                Places you saved for your next Malaysian adventure.
            </p>

        </div>

        <a
            href="{{ url('/profile') }}"
            class="btn-back"
        >
            ← Back to Profile
        </a>

    </div>


    @if(session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    @if(session('error'))

        <div class="alert alert-danger">
            {{ session('error') }}
        </div>

    @endif


    @if($savedPlaces->count() > 0)

        <div class="row g-4">

            @foreach($savedPlaces as $place)

                @php
                    $attraction = $place->attraction;
                    $firstImage = $attraction->images->first();
                @endphp

                <div class="col-12 col-md-6 col-lg-4">

                    <article class="place-card">

                        {{-- Attraction Image --}}

                        <a
                            href="{{ route('attractions.show', $attraction->attraction_id) }}"
                            class="place-image-link"
                        >

                            @if($firstImage)

                                <img
                                    src="{{ asset($firstImage->image_path) }}"
                                    alt="{{ $attraction->attraction_name }}"
                                    class="place-image"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >

                                <div
                                    class="place-image-placeholder"
                                    style="display:none;"
                                >
                                    ExploreMY
                                </div>

                            @else

                                <div class="place-image-placeholder">
                                    ExploreMY
                                </div>

                            @endif

                        </a>


                        <div class="place-content">

                            {{-- Category + Heart --}}

                            <div class="place-card-top">

                                <div class="place-categories">

                                    @forelse($attraction->preferences as $preference)

                                        <span class="place-category">
                                            {{ $preference->category_name }}
                                        </span>

                                    @empty

                                        <span class="place-category">
                                            Uncategorized
                                        </span>

                                    @endforelse

                                </div>

                                <span
                                    class="saved-heart"
                                    aria-label="Saved place"
                                >
                                    ♥
                                </span>

                            </div>


                            {{-- Attraction Name --}}

                            <h2>
                                {{ $attraction->attraction_name }}
                            </h2>


                            {{-- Location --}}

                            <p class="place-location">
                                📍
                                {{ $attraction->location ?: ($attraction->state->state_name ?? 'Malaysia') }}
                            </p>


                            {{-- Description --}}

                            <p class="place-description">

                                @if($attraction->description)

                                    {{ $attraction->description }}

                                @else

                                    No description is available for this attraction.

                                @endif

                            </p>


                            {{-- Buttons --}}

                            <div class="place-actions">

                                <a
                                    href="{{ route('attractions.show', $attraction->attraction_id) }}"
                                    class="view-place-button"
                                >
                                    View Attraction
                                </a>


                                <form
                                    method="POST"
                                    action="{{ route('attractions.wishlist.remove', $attraction->attraction_id) }}"
                                    onsubmit="return confirm('Remove this attraction from your wishlist?');"
                                >

                                    @csrf

                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="remove-place-button"
                                    >
                                        Remove
                                    </button>

                                </form>

                            </div>

                        </div>

                    </article>

                </div>

            @endforeach

        </div>

    @else

        <div class="empty-state">

            <div class="empty-heart">
                ♡
            </div>

            <h2>
                No saved places yet
            </h2>

            <p>
                Your saved destinations will appear here.
            </p>

            <a
                href="{{ route('attractions.index') }}"
                class="explore-button"
            >
                Explore Attractions
            </a>

        </div>

    @endif

</main>

</body>
</html>