<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Explore Attractions | ExploreMY</title>

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
        href="{{ asset('css/attractions.css') }}"
    >
</head>

<body>

@include('components.navbar')

<main class="container attractions-page">

    <section class="explore-heading">

        <p class="page-kicker">
            Discover Malaysia
        </p>

        <h1>
            Explore Attractions
        </h1>

        <p class="page-description">
            Find places that match what you want to experience on your next trip.
        </p>

    </section>

    <section class="search-panel">

        @if($errors->any())

            <div class="validation-errors">
                <strong>Please check the following:</strong>

                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>

        @endif

        <form
            method="GET"
            action="{{ route('attractions.index') }}"
        >

            <input
                type="hidden"
                name="search_submitted"
                value="1"
            >

            <div class="search-main">

                <div class="search-field search-field-large">

                    <label for="search">
                        What place are you looking for?
                    </label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            ⌕
                        </span>

                        <input
                            type="text"
                            id="search"
                            name="search"
                            value="{{ old('search', request('search')) }}"
                            placeholder="e.g. LEGOLAND, KL Tower, beach..."
                            class="{{ $errors->has('search') ? 'input-error' : '' }}"
                        >

                    </div>

                    @if($errors->has('search'))
                        <p class="field-error">
                            {{ $errors->first('search') }}
                        </p>
                    @endif

                </div>

                <button
                    type="submit"
                    class="search-button"
                >
                    Search
                </button>

            </div>

            @php
                $hasFilters =
                    request()->filled('state_id') ||
                    request()->filled('budget_level') ||
                    request()->filled('rating') ||
                    !empty(request('categories'));
            @endphp

            <div class="filter-divider"></div>

            <div class="filter-header">

                <div>

                    <h3>
                        Filter your results
                    </h3>

                    <p>
                        All filters are optional.
                    </p>

                </div>

                @if($hasFilters)

                    <a
                        href="{{ route('attractions.index', [
                            'search_submitted' => request('search_submitted'),
                            'search' => request('search'),
                        ]) }}"
                        class="clear-filter"
                    >
                        Clear filters
                    </a>

                @endif

            </div>

            <div class="filter-grid">

                <div class="filter-group">

                    <label for="state_id">
                        State
                    </label>

                    <select
                        name="state_id"
                        id="state_id"
                    >

                        <option value="">
                            All states
                        </option>

                        @foreach($states as $state)

                            <option
                                value="{{ $state->state_id }}"
                                {{ request('state_id') == $state->state_id ? 'selected' : '' }}
                            >
                                {{ $state->state_name }}
                            </option>

                        @endforeach

                    </select>

                </div>

                <div class="filter-group category-filter">

                    <label>
                        Category
                    </label>

                    <div class="category-options">

                        @foreach($categories as $category)

                            <label class="category-option">

                                <input
                                    type="checkbox"
                                    name="categories[]"
                                    value="{{ $category->preference_id }}"
                                    {{ in_array(
                                        $category->preference_id,
                                        array_map(
                                            'intval',
                                            request('categories', [])
                                        )
                                    ) ? 'checked' : '' }}
                                >

                                <span>
                                    {{ $category->category_name }}
                                </span>

                            </label>

                        @endforeach

                    </div>

                </div>

                <div class="filter-group">

                    <label for="budget_level">
                        Budget
                    </label>

                    <select
                        name="budget_level"
                        id="budget_level"
                    >

                        <option value="">
                            Any budget
                        </option>

                        <option
                            value="Low"
                            {{ request('budget_level') === 'Low' ? 'selected' : '' }}
                        >
                            Low
                        </option>

                        <option
                            value="Medium"
                            {{ request('budget_level') === 'Medium' ? 'selected' : '' }}
                        >
                            Medium
                        </option>

                        <option
                            value="High"
                            {{ request('budget_level') === 'High' ? 'selected' : '' }}
                        >
                            High
                        </option>

                    </select>

                </div>

                <div class="filter-group">

                    <label for="rating">
                        Minimum rating
                    </label>

                    <select
                        name="rating"
                        id="rating"
                    >

                        <option value="">
                            Any rating
                        </option>

                        <option
                            value="4.5"
                            {{ request('rating') == '4.5' ? 'selected' : '' }}
                        >
                            4.5 ★ and above
                        </option>

                        <option
                            value="4.0"
                            {{ request('rating') == '4.0' ? 'selected' : '' }}
                        >
                            4.0 ★ and above
                        </option>

                        <option
                            value="3.5"
                            {{ request('rating') == '3.5' ? 'selected' : '' }}
                        >
                            3.5 ★ and above
                        </option>

                        <option
                            value="3.0"
                            {{ request('rating') == '3.0' ? 'selected' : '' }}
                        >
                            3.0 ★ and above
                        </option>

                    </select>

                </div>

            </div>

        </form>

    </section>

    <section class="results-section">

        <div class="results-heading">

            <div>

                @if($searchSubmitted && request('search'))

                    <p class="results-kicker">
                        Search results
                    </p>

                    <h2>
                        Results for "{{ request('search') }}"
                    </h2>

                @elseif(!$searchSubmitted)

                    @if(
                        \App\Models\UserPreference::where(
                            'user_id',
                            Auth::id()
                        )->exists()
                    )

                        <p class="results-kicker">
                            Recommended for you
                        </p>

                        <h2>
                            Based on your travel preferences
                        </h2>

                    @else

                        <p class="results-kicker">
                            Popular attractions
                        </p>

                        <h2>
                            Explore Malaysia
                        </h2>

                    @endif

                @else

                    <p class="results-kicker">
                        Search results
                    </p>

                    <h2>
                        Explore Malaysia
                    </h2>

                @endif

            </div>

            <span class="result-count">
                {{ $attractions->total() }}
                {{ $attractions->total() == 1 ? 'place' : 'places' }}
            </span>

        </div>

        @if($attractions->count() > 0)

            <div class="attraction-grid">

                @foreach($attractions as $attraction)

                    <article class="attraction-card">

                        <div class="attraction-image-wrapper">

                            @if($attraction->images->isNotEmpty())

                                <img
                                    src="{{ asset($attraction->images->first()->image_path) }}"
                                    alt="{{ $attraction->attraction_name }}"
                                    class="attraction-image"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >

                                <div
                                    class="image-placeholder"
                                    style="display:none;"
                                >
                                    <span>
                                        ExploreMY
                                    </span>
                                </div>

                            @else

                                <div class="image-placeholder">

                                    <span>
                                        ExploreMY
                                    </span>

                                </div>

                            @endif

                            @if($attraction->rating)

                                <div class="rating-badge">
                                    ★ {{ number_format((float) $attraction->rating, 1) }}
                                </div>

                            @endif

                        </div>

                        <div class="attraction-content">

                            <div class="attraction-top">

                                <div class="attraction-categories">

                                    @forelse($attraction->preferences as $preference)

                                        <span class="attraction-category">
                                            {{ $preference->category_name }}
                                        </span>

                                    @empty

                                        <span class="attraction-category">
                                            Uncategorized
                                        </span>

                                    @endforelse

                                </div>

                                <span class="budget-badge">
                                    {{ $attraction->budget_level ?: 'Price unavailable' }}
                                </span>

                            </div>

                            <h3>
                                {{ $attraction->attraction_name }}
                            </h3>

                            <p class="attraction-location">
                                📍 {{ $attraction->state->state_name ?? 'Malaysia' }}
                            </p>

                            <p class="attraction-description">
                                {{ Str::limit($attraction->description, 100) }}
                            </p>

                            <div class="attraction-card-actions">

                                <a
                                    href="{{ route('attractions.show', $attraction->attraction_id) }}"
                                    class="details-button"
                                >
                                    View Attraction
                                    <span>→</span>
                                </a>

                                @if(in_array((int) $attraction->attraction_id, $wishlistedAttractionIds, true))

                                    <form method="POST" action="{{ route('attractions.wishlist.remove', $attraction->attraction_id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="card-wishlist-button is-saved" aria-label="Remove {{ $attraction->attraction_name }} from wishlist">♥ Saved</button>
                                    </form>

                                @else

                                    <form method="POST" action="{{ route('attractions.wishlist.add', $attraction->attraction_id) }}">
                                        @csrf
                                        <button type="submit" class="card-wishlist-button" aria-label="Add {{ $attraction->attraction_name }} to wishlist">♡ Save</button>
                                    </form>

                                @endif

                            </div>

                        </div>

                    </article>

                @endforeach

            </div>

            @if($attractions->hasPages())

                <nav
                    class="custom-pagination"
                    aria-label="Attraction pages"
                >

                    @if($attractions->onFirstPage())

                        <span class="page-arrow disabled">
                            ←
                        </span>

                    @else

                        <a
                            href="{{ $attractions->appends(request()->query())->previousPageUrl() }}"
                            class="page-arrow"
                        >
                            ←
                        </a>

                    @endif

                    @php

                        $current = $attractions->currentPage();
                        $last = $attractions->lastPage();
                        $pages = [];

                        $pages[] = 1;

                        if ($current > 4) {
                            $pages[] = '...';
                        }

                        for (
                            $page = max(2, $current - 1);
                            $page <= min($last - 1, $current + 1);
                            $page++
                        ) {
                            $pages[] = $page;
                        }

                        if ($current < $last - 3) {
                            $pages[] = '...';
                        }

                        if ($last > 1) {
                            $pages[] = $last;
                        }

                        $pages = array_values(array_unique($pages));

                    @endphp

                    @foreach($pages as $page)

                        @if($page === '...')

                            <span class="page-dots">
                                ...
                            </span>

                        @elseif($page == $current)

                            <span class="page-number active">
                                {{ $page }}
                            </span>

                        @else

                            <a
                                href="{{ $attractions->appends(request()->query())->url($page) }}"
                                class="page-number"
                            >
                                {{ $page }}
                            </a>

                        @endif

                    @endforeach

                    @if($attractions->hasMorePages())

                        <a
                            href="{{ $attractions->appends(request()->query())->nextPageUrl() }}"
                            class="page-arrow"
                        >
                            →
                        </a>

                    @else

                        <span class="page-arrow disabled">
                            →
                        </span>

                    @endif

                </nav>

            @endif

        @elseif($searchSubmitted)

            <div class="no-results">

                <div class="no-results-icon">
                    🔎
                </div>

                <h2>
                    No attractions found
                </h2>

                <p>
                    We couldn't find an attraction matching your search and filters.
                </p>

                <a
                    href="{{ route('attractions.index') }}"
                    class="search-again-button"
                >
                    Search Again
                </a>

            </div>

        @else

            <div class="no-results">

                <div class="no-results-icon">
                    🔎
                </div>

                <h2>
                    No recommendations available
                </h2>

                <p>
                    Start a search by entering a place you want to explore.
                </p>

            </div>

        @endif

    </section>

</main>


@include('components.footer')

</body>
</html>
