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
@php($fromSavedPlaces = request('source') === 'saved')
<x-page-back
    :href="$fromSavedPlaces ? route('saved-places.index') : route('attractions.index')"
    :label="$fromSavedPlaces ? __('route_form.back_to_saved_places') : __('attraction.back')"
/>

<main class="attraction-show-page">

    <div class="container">

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

            <div class="hero-image-section" data-gallery-root>

                @if($attraction->images->isNotEmpty())

                    <div class="gallery-image-data" hidden aria-hidden="true">
                        @foreach($attraction->images as $galleryImage)
                            <span class="js-gallery-image" data-src="{{ asset($galleryImage->image_path) }}"></span>
                        @endforeach
                    </div>

                    <div class="main-image-wrapper">

                        <img
                            id="mainAttractionImage"
                            src="{{ asset($attraction->images->first()->image_path) }}"
                            alt="{{ $attraction->attraction_name }}"
                            class="main-attraction-image"
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

                                    @php($dotIndex = $loop->index)
                                    @php($dotActive = $loop->first ? 'active' : '')
                                    @php($galleryLabel = __('explore.show_image', ['current' => $loop->iteration, 'total' => $loop->count]))
                                    <button
                                        type="button"
                                        class="image-dot js-image-dot {{ $dotActive }}"
                                        data-image-index="{{ $dotIndex }}"
                                        aria-label="{{ $galleryLabel }}"
                                    ></button>

                                @endforeach

                            </div>

                        @endif

                    </div>

                    @if($attraction->images->count() > 1)

                        <div class="gallery-controls">

                            <button type="button" id="galleryPrevButton">
                    &larr; {{ __('attraction.previous') }}
                            </button>

                            @php($galleryTotal = $attraction->images->count())
                            @php($photoTemplate = __('attraction.photo', ['current' => ':current', 'total' => $galleryTotal]))
                            @php($photoInitial = __('attraction.photo', ['current' => 1, 'total' => $galleryTotal]))
                            <span id="imagePosition" data-photo-template="{{ $photoTemplate }}">
                    {{ $photoInitial }}
                            </span>

                            <button type="button" id="galleryNextButton">
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
                            data-ajax-crud
                            data-ajax-wishlist
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
                            data-ajax-crud
                            data-ajax-wishlist
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
                            id="saveAttractionLink"
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

        </div>

    </div>

</main>

<script>
    (function () {
        var galleryItems = document.querySelectorAll('.js-gallery-image');
        var attractionImages = [];
        var index = 0;
        for (index = 0; index < galleryItems.length; index += 1) {
            attractionImages.push(galleryItems[index].getAttribute('data-src'));
        }
        var activeImageIndex = 0;
        var mainImage = document.getElementById('mainAttractionImage');
        var placeholder = document.getElementById('mainImagePlaceholder');
        var imagePosition = document.getElementById('imagePosition');
        var photoTemplate = imagePosition ? imagePosition.getAttribute('data-photo-template') : '';

        function renderPosition() {
            if (!imagePosition || !photoTemplate) {
                return;
            }
            imagePosition.textContent = photoTemplate
                .replace(':current', String(activeImageIndex + 1))
                .replace(':total', String(attractionImages.length));
        }

        function showImage(imageIndex) {
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
                .querySelectorAll('.js-image-dot')
                .forEach(function (dot, index) {
                    dot.classList.toggle('active', index === activeImageIndex);
                });

            renderPosition();
        }

        document
            .querySelectorAll('.js-image-dot')
            .forEach(function (dot) {
                dot.addEventListener('click', function () {
                    showImage(Number(dot.getAttribute('data-image-index') || '0'));
                });
            });

        var prevButton = document.getElementById('galleryPrevButton');
        var nextButton = document.getElementById('galleryNextButton');

        if (prevButton) {
            prevButton.addEventListener('click', function () {
                showImage(activeImageIndex - 1);
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                showImage(activeImageIndex + 1);
            });
        }

        if (mainImage) {
            mainImage.addEventListener('error', function () {
                mainImage.style.display = 'none';
                if (placeholder) {
                    placeholder.style.display = 'flex';
                }
            });
        }

        var saveLink = document.getElementById('saveAttractionLink');

        if (saveLink) {
            saveLink.addEventListener('click', function (event) {
                event.preventDefault();
                var wishlistButton = document.querySelector('.wishlist-button');
                if (wishlistButton) {
                    wishlistButton.click();
                }
            });
        }
    })();
</script>

</body>
</html>
