<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $attraction->attraction_name }} | ExploreMY</title>

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
        href="{{ asset('css/attraction-show.css') }}"
    >
</head>

<body>

@include('components.navbar')

<main class="attraction-show-page">

    <div class="container">

        <div class="back-row">

            <a
                href="{{ url('/attractions') }}"
                class="back-button"
            >
                ← Back to Explore
            </a>

        </div>

        @if(session('success'))

            <div class="success-message">
                {{ session('success') }}
            </div>

        @endif

        @if(session('error'))

            <div class="error-message">
                {{ session('error') }}
            </div>

        @endif

        <section class="attraction-hero">

            <div class="hero-image-section">

                @if($attraction->images->isNotEmpty())

                    <div class="main-image-wrapper">

                        <img
                            id="mainAttractionImage"
                            src="{{ asset($attraction->images->first()->image_path) }}"
                            alt="{{ $attraction->attraction_name }}"
                            class="main-attraction-image"
                            onerror="this.style.display='none'; document.getElementById('mainImagePlaceholder').style.display='flex';"
                        >

                        <div
                            id="mainImagePlaceholder"
                            class="main-image-placeholder"
                            style="display:none;"
                        >
                            <span>
                                ExploreMY
                            </span>
                        </div>

                        @if($attraction->rating)

                            <div class="hero-rating">
                                ★ {{ number_format((float) $attraction->rating, 1) }}
                            </div>

                        @endif

                    </div>

                    @if($attraction->images->count() > 1)

                        <div class="image-thumbnails">

                            @foreach($attraction->images as $image)

                                <button
                                    type="button"
                                    class="image-thumbnail {{ $loop->first ? 'active' : '' }}"
                                    onclick="changeMainImage('{{ asset($image->image_path) }}', this)"
                                >

                                    <img
                                        src="{{ asset($image->image_path) }}"
                                        alt="{{ $attraction->attraction_name }}"
                                    >

                                </button>

                            @endforeach

                        </div>

                    @endif

                @else

                    <div class="main-image-placeholder">
                        <span>
                            ExploreMY
                        </span>
                    </div>

                @endif

            </div>

            <div class="hero-content">

                <div class="category-list">

                    @forelse($attraction->preferences as $preference)

                        <span class="category-badge">
                            {{ $preference->category_name }}
                        </span>

                    @empty

                        <span class="category-badge">
                            Uncategorized
                        </span>

                    @endforelse

                </div>

                <h1>
                    {{ $attraction->attraction_name }}
                </h1>

                <div class="hero-location">

                    <span>
                        📍
                    </span>

                    <span>
                        {{ $attraction->location ?: ($attraction->state->state_name ?? 'Malaysia') }}
                    </span>

                </div>

                <div class="hero-actions">

                    @if($isWishlisted)

                        <form
                            method="POST"
                            action="{{ route('attractions.wishlist.remove', $attraction->attraction_id) }}"
                        >

                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="wishlist-button wishlisted"
                            >
                                ♥ Remove from Wishlist
                            </button>

                        </form>

                    @else

                        <form
                            method="POST"
                            action="{{ route('attractions.wishlist.add', $attraction->attraction_id) }}"
                        >

                            @csrf

                            <button
                                type="submit"
                                class="wishlist-button"
                            >
                                ♡ Add to Wishlist
                            </button>

                        </form>

                    @endif

                </div>

            </div>

        </section>

        <section class="information-grid">

            <div class="main-information">

                <div class="information-card">

                    <div class="section-title">

                        <p class="section-kicker">
                            About this place
                        </p>

                        <h2>
                            About {{ $attraction->attraction_name }}
                        </h2>

                    </div>

                    <div class="description">

                        @if($attraction->description)

                            <p>
                                {{ $attraction->description }}
                            </p>

                        @else

                            <p class="empty-text">
                                No description is available for this attraction.
                            </p>

                        @endif

                    </div>

                </div>

                <div class="information-card">

                    <div class="section-title">

                        <p class="section-kicker">
                            Plan your visit
                        </p>

                        <h2>
                            Visitor Information
                        </h2>

                    </div>

                    <div class="details-grid">

                        <div class="detail-item">

                            <div class="detail-icon">
                                📍
                            </div>

                            <div>

                                <span class="detail-label">
                                    Location
                                </span>

                                <p>
                                    {{ $attraction->location ?: 'Location unavailable' }}
                                </p>

                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-icon">
                                💰
                            </div>

                            <div>

                                <span class="detail-label">
                                    Budget
                                </span>

                                <p>
                                    {{ $attraction->budget_level ?: 'Price unavailable' }}
                                </p>

                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-icon">
                                🕐
                            </div>

                            <div>

                                <span class="detail-label">
                                    Opening Hours
                                </span>

                                @if($attraction->operating_hours)

                                    <p class="opening-hours">
                                        {!! nl2br(e($attraction->operating_hours)) !!}
                                    </p>

                                @else

                                    <p>
                                        Opening hours unavailable
                                    </p>

                                @endif

                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-icon">
                                🚌
                            </div>

                            <div>

                                <span class="detail-label">
                                    Nearby Transport
                                </span>

                                <p>
                                    {{ $attraction->nearby_transport ?: 'Transport information unavailable' }}
                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <aside class="side-information">

                <div class="rating-card">

                    <span class="side-card-label">
                        Google Rating
                    </span>

                    <div class="large-rating">

                        <span class="star">
                            ★
                        </span>

                        <span>
                            {{ $attraction->rating ? number_format((float) $attraction->rating, 1) : 'N/A' }}
                        </span>

                    </div>

                    @if($attraction->rating)

                        <p>
                            Rated {{ number_format((float) $attraction->rating, 1) }} out of 5
                        </p>

                    @else

                        <p>
                            Rating unavailable
                        </p>

                    @endif

                </div>

                <div class="side-card">

                    <span class="side-card-label">
                        Attraction Type
                    </span>

                    <div class="side-category-list">

                        @forelse($attraction->preferences as $preference)

                            <span>
                                {{ $preference->category_name }}
                            </span>

                        @empty

                            <span>
                                Uncategorized
                            </span>

                        @endforelse

                    </div>

                </div>

                <div class="side-card">

                    <span class="side-card-label">
                        State
                    </span>

                    <h3>
                        {{ $attraction->state->state_name ?? 'Malaysia' }}
                    </h3>

                </div>

                <div class="side-card visit-card">

                    <span class="visit-icon">
                        ✈
                    </span>

                    <h3>
                        Planning a trip?
                    </h3>

                    <p>
                        Save this attraction to your wishlist so you can find it again later.
                    </p>

                    @if($isWishlisted)

                        <span class="saved-message">
                            ♥ Saved to your wishlist
                        </span>

                    @else

                        <a
                            href="#"
                            onclick="document.querySelector('.wishlist-button').click(); return false;"
                            class="save-link"
                        >
                            Save this attraction →
                        </a>

                    @endif

                </div>

            </aside>

        </section>

        <div class="bottom-back">

            <a
                href="{{ route('attractions.index') }}"
                class="back-button"
            >
                ← Explore More Attractions
            </a>

            <a
                href="{{ route('saved-places.index') }}"
                class="back-button secondary-back-button"
            >
                ♡ Saved Places
            </a>

        </div>

    </div>

</main>

<script>
    function changeMainImage(imageUrl, thumbnail) {
        const mainImage = document.getElementById('mainAttractionImage');
        const placeholder = document.getElementById('mainImagePlaceholder');

        mainImage.src = imageUrl;
        mainImage.style.display = 'block';

        if (placeholder) {
            placeholder.style.display = 'none';
        }

        document
            .querySelectorAll('.image-thumbnail')
            .forEach(function(item) {
                item.classList.remove('active');
            });

        thumbnail.classList.add('active');
    }
</script>

</body>
</html>