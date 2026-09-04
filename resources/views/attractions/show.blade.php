<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
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
<x-page-back :href="route('attractions.index')" :label="__('attraction.back')" />

<main class="attraction-show-page">

    <div class="container">

        <div class="back-row">

            <a
                href="{{ route('saved-places.index') }}"
                class="details-nav-button details-nav-saved"
            aria-label="{{ __('explore.saved_places') }}"
            >
            ♥ {{ __('attraction.saved_places') }}
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

                        @if($attraction->images->count() > 1)

            <div class="image-dots" aria-label="{{ __('explore.images') }}">

                                @foreach($attraction->images as $image)

                                    <button
                                        type="button"
                                        class="image-dot {{ $loop->first ? 'active' : '' }}"
                                        onclick="showImage({{ $loop->index }})"
                        aria-label="{{ __('explore.show_image', ['current' => $loop->iteration, 'total' => $loop->count]) }}"
                                    ></button>

                                @endforeach

                            </div>

                        @endif

                    </div>

                    @if($attraction->images->count() > 1)

                        <div class="gallery-controls">

                            <button type="button" onclick="showPreviousImage()">
                    &larr; {{ __('attraction.previous') }}
                            </button>

                            <span id="imagePosition">
                    {{ __('attraction.photo', ['current' => 1, 'total' => $attraction->images->count()]) }}
                            </span>

                            <button type="button" onclick="showNextImage()">
                    {{ __('attraction.next') }} &rarr;
                            </button>

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
                            {{ $preference->localized_name }}
                        </span>

                    @empty

                        <span class="category-badge">
                            {{ __('explore.uncategorized') }}
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
                        ♥ {{ __('attraction.remove_wishlist') }}
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
                        ♡ {{ __('attraction.add_wishlist') }}
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
                        {{ __('attraction.about_place') }}
                        </p>

                        <h2>
                    {{ __('attraction.about', ['name' => $attraction->attraction_name]) }}
                        </h2>

                    </div>

                    <div class="description">

                        @if($attraction->description)

                            <p>
                                {{ $attraction->description }}
                            </p>

                        @else

                            <p class="empty-text">
                        {{ __('attraction.no_description') }}
                            </p>

                        @endif

                    </div>

                </div>

                <div class="information-card">

                    <div class="section-title">

                        <p class="section-kicker">
                        {{ __('attraction.plan') }}
                        </p>

                        <h2>
                    {{ __('attraction.visitor') }}
                        </h2>

                    </div>

                    <div class="details-grid">

                        <div class="detail-item">

                            <div class="detail-icon">
                                📍
                            </div>

                            <div>

                                <span class="detail-label">
                            {{ __('attraction.location') }}
                                </span>

                                <p>
                            {{ $attraction->location ?: __('attraction.location_unavailable') }}
                                </p>

                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-icon">
                                💰
                            </div>

                            <div>

                                <span class="detail-label">
                            {{ __('attraction.budget') }}
                                </span>

                                <p>
                            {{ $attraction->budget_level ? __('explore.' . strtolower($attraction->budget_level)) : __('attraction.price_unavailable') }}
                                </p>

                            </div>

                        </div>

                        <div class="detail-item">

                            <div class="detail-icon">
                                🕐
                            </div>

                            <div>

                                <span class="detail-label">
                            {{ __('attraction.hours') }}
                                </span>

                                @if($attraction->operating_hours)

                                    <p class="opening-hours">
                                        {!! nl2br(e($attraction->operating_hours)) !!}
                                    </p>

                                @else

                                    <p>
                                {{ __('attraction.hours_unavailable') }}
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
                            {{ __('attraction.transport') }}
                                </span>

                                <p>
                            {{ $attraction->nearby_transport ?: __('attraction.transport_unavailable') }}
                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <aside class="side-information">

                <div class="rating-card">

                    <span class="side-card-label">
                    {{ __('attraction.google_rating') }}
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
                    {{ __('attraction.rated', ['rating' => number_format((float) $attraction->rating, 1)]) }}
                        </p>

                    @else

                        <p>
                    {{ __('attraction.rating_unavailable') }}
                        </p>

                    @endif

                </div>

                <div class="side-card">

                    <span class="side-card-label">
                    {{ __('attraction.type') }}
                    </span>

                    <div class="side-category-list">

                        @forelse($attraction->preferences as $preference)

                            <span>
                            {{ $preference->localized_name }}
                            </span>

                        @empty

                            <span>
                            {{ __('explore.uncategorized') }}
                            </span>

                        @endforelse

                    </div>

                </div>

                <div class="side-card">

                    <span class="side-card-label">
                    {{ __('attraction.state') }}
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
                {{ __('attraction.planning') }}
                    </h3>

                    <p>
                {{ __('attraction.save_intro') }}
                    </p>

                    @if($isWishlisted)

                        <span class="saved-message">
                    ♥ {{ __('attraction.saved') }}
                        </span>

                    @else

                        <a
                            href="#"
                            onclick="document.querySelector('.wishlist-button').click(); return false;"
                            class="save-link"
                        >
                    {{ __('attraction.save') }} →
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
            ← {{ __('attraction.explore_more') }}
            </a>

            <a
                href="{{ route('saved-places.index') }}"
                class="back-button secondary-back-button"
            >
            ♡ {{ __('attraction.saved_places') }}
            </a>

        </div>

    </div>

</main>

<script>
const attractionImages = @json($attraction->images->map(fn ($image) => asset($image->image_path))->values());
const photoLabel = {{ Illuminate\Support\Js::from(__('attraction.photo', ['current' => ':current', 'total' => ':total'])) }};
    let activeImageIndex = 0;

    function showImage(imageIndex) {
        const mainImage = document.getElementById('mainAttractionImage');
        const placeholder = document.getElementById('mainImagePlaceholder');

        if (!mainImage || attractionImages.length === 0) {
            return;
        }

        activeImageIndex = (imageIndex + attractionImages.length) % attractionImages.length;
        mainImage.src = attractionImages[activeImageIndex];
        mainImage.style.display = 'block';

        if (placeholder) {
            placeholder.style.display = 'none';
        }

        document
            .querySelectorAll('.image-dot')
            .forEach(function(dot, index) {
                dot.classList.toggle('active', index === activeImageIndex);
            });

        const imagePosition = document.getElementById('imagePosition');

        if (imagePosition) {
        imagePosition.textContent = photoLabel.replace(':current', activeImageIndex + 1).replace(':total', attractionImages.length);
        }
    }

    function showNextImage() {
        showImage(activeImageIndex + 1);
    }

    function showPreviousImage() {
        showImage(activeImageIndex - 1);
    }
</script>

</body>
</html>
