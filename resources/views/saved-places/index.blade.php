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
            href="{{ url()->previous() }}"
            class="btn-back"
            aria-label="Back to previous page"
        >
            ← Back to Profile
        </a>

    </div>

    @if($savedPlaces->count() > 0)
        <section class="collections-panel">
            <div class="collections-heading">
                <div>
                    <p class="page-kicker">Plan your way</p>
                    <h2>Create a custom collection</h2>
                    <p>Choose saved places for a trip, then give the collection a name.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('saved-places.collections.store') }}" class="collection-form">
                @csrf
                <label for="collection-name">Collection name</label>
                <input id="collection-name" name="name" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Langkawi weekend" required>

                <fieldset>
                    <legend>Select saved places</legend>
                    <div class="collection-place-options">
                        @foreach($savedPlaces as $place)
                            <label class="collection-place-option">
                                <input type="checkbox" name="wishlist_ids[]" value="{{ $place->wishlist_id }}" {{ in_array($place->wishlist_id, old('wishlist_ids', [])) ? 'checked' : '' }}>
                                <span>{{ $place->attraction->attraction_name }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @error('name')<p class="collection-error">{{ $message }}</p>@enderror
                @error('wishlist_ids')<p class="collection-error">{{ $message }}</p>@enderror

                <button type="submit">Create collection</button>
            </form>

            @if($collections->isNotEmpty())
                <div class="collection-list">
                    @foreach($collections as $collection)
                        <article class="collection-card">
                            <div class="collection-card-main">
                                <div class="collection-card-heading">
                                    <div>
                                        <span>{{ $collection->items_count }} {{ $collection->items_count === 1 ? 'place' : 'places' }}</span>
                                        <h3>{{ $collection->name }}</h3>
                                    </div>
                                    @if($collection->items_count >= 2)
                                        <a href="{{ route('route.index', ['source' => 'saved', 'collection' => $collection->collection_id]) }}">Generate trip plan &rarr;</a>
                                    @endif
                                </div>

                                <div class="collection-attractions">
                                    @foreach($collection->items as $item)
                                        @if($item->wishlist && $item->wishlist->attraction)
                                            <a href="{{ route('attractions.show', $item->wishlist->attraction->attraction_id) }}">
                                                @if($item->wishlist->attraction->images->isNotEmpty())
                                                    <img src="{{ asset($item->wishlist->attraction->images->first()->image_path) }}" alt="">
                                                @else
                                                    <span class="collection-image-fallback">ExploreMY</span>
                                                @endif
                                                <span>{{ $item->wishlist->attraction->attraction_name }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>

                                @php $collectionWishlistIds = $collection->items->pluck('wishlist_id')->all(); @endphp
                                @if($savedPlaces->whereNotIn('wishlist_id', $collectionWishlistIds)->isNotEmpty())
                                    <details class="add-to-collection">
                                        <summary>Add saved places</summary>
                                        <form method="POST" action="{{ route('saved-places.collections.places.store', $collection->collection_id) }}">
                                            @csrf
                                            <div class="collection-place-options">
                                                @foreach($savedPlaces->whereNotIn('wishlist_id', $collectionWishlistIds) as $place)
                                                    <label class="collection-place-option">
                                                        <input type="checkbox" name="wishlist_ids[]" value="{{ $place->wishlist_id }}">
                                                        <span>{{ $place->attraction->attraction_name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <button type="submit">Add to collection</button>
                                        </form>
                                    </details>
                                @endif
                            </div>

                            @if($collection->items_count < 2)<p class="collection-trip-note">Add one more place to generate a trip.</p>@endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @if($savedPlaces->count() > 0)
        <section class="trip-cta">
            <div>
                <span>Ready to turn favourites into a journey?</span>
                <h2>Start Your Trip Now</h2>
                <p>Use your saved places to generate an optimised itinerary.</p>
            </div>
            @if($savedPlaces->count() >= 2)
                <a href="{{ route('route.index', ['source' => 'saved']) }}">
                    Generate Itinerary &rarr;
                </a>
            @else
                <p class="trip-cta-instruction">
                    <span aria-hidden="true">!</span>
                    <span>
                        <strong>Save one more place to start your trip</strong>
                        You need at least two saved places to generate an itinerary.
                    </span>
                </p>
            @endif
        </section>
    @endif


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
