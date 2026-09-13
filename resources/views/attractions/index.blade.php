<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ __('explore.title') }}</title>

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
@auth
<x-page-back :href="route('dashboard')" :label="__('ui.profile.back')" />
@endauth

<main class="container attractions-page">

    <section class="explore-heading">

        <p class="page-kicker">
            {{ __('explore.discover') }}
        </p>

        <h1>
            {{ __('explore.heading') }}
        </h1>

        <p class="page-description">
            {{ __('explore.intro') }}
        </p>

    </section>

    <section class="search-panel">

        @if(!session('success') && !empty($searchSuccessMessage ?? null))

            <div class="alert-success" id="searchSuccessPopup" role="status">
                {{ $searchSuccessMessage }}
            </div>

        @endif

        @if(session('success'))

            <div class="alert-success" id="filterSuccessPopup">
                {{ session('success') }}
            </div>

        @endif

        @if($errors->any())

            <div class="validation-errors">
                <strong>{{ __('explore.check') }}</strong>

                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>

        @endif

        <p id="empty-search-error" role="alert" tabindex="-1" hidden style="color: #b42318;">{{ __('explore.empty_search') }}</p>
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
                        {{ __('explore.looking') }}
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
                            placeholder="{{ __('explore.placeholder') }}"
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
                    {{ __('explore.search') }}
                </button>

            </div>

            @php
                $hasFilters =
                    request()->filled('search') ||
                    request()->filled('state_id') ||
                    request()->filled('budget_level') ||
                    request()->filled('rating') ||
                    !empty(request('categories'));
            @endphp

            <div class="filter-divider"></div>

            <div class="filter-header">

                <div>

                    <h3>
                        {{ __('explore.filter') }}
                    </h3>

                    <p>
                        {{ __('explore.optional') }}
                    </p>

                </div>

                <a
                    href="{{ route('attractions.index', array_merge(request()->query(), ['clear' => '1'])) }}"
                    class="clear-filter"
                    id="clearFiltersLink"
                >
                    {{ __('explore.clear') }}
                </a>

            </div>

            <div class="filter-grid">

                <div class="filter-group">

                    <label for="state_id">
                        {{ __('explore.state') }}
                    </label>

                    <select
                        name="state_id"
                        id="state_id"
                    >

                        <option value="">
                            {{ __('explore.all_states') }}
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
                        {{ __('explore.category') }}
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
                                    {{ $category->localized_name }}
                                </span>

                            </label>

                        @endforeach

                    </div>

                </div>

                <div class="filter-group">

                    <label for="budget_level">
                        {{ __('explore.budget') }}
                    </label>

                    <select
                        name="budget_level"
                        id="budget_level"
                    >

                        <option value="">
                            {{ __('explore.any_budget') }}
                        </option>

                        <option
                            value="Low"
                            {{ request('budget_level') === 'Low' ? 'selected' : '' }}
                        >
                            {{ __('explore.low') }}
                        </option>

                        <option
                            value="Medium"
                            {{ request('budget_level') === 'Medium' ? 'selected' : '' }}
                        >
                            {{ __('explore.medium') }}
                        </option>

                        <option
                            value="High"
                            {{ request('budget_level') === 'High' ? 'selected' : '' }}
                        >
                            {{ __('explore.high') }}
                        </option>

                    </select>

                </div>

                <div class="filter-group">

                    <label for="rating">
                        {{ __('explore.minimum_rating') }}
                    </label>

                    <select
                        name="rating"
                        id="rating"
                    >

                        <option value="">
                            {{ __('explore.any_rating') }}
                        </option>

                        <option
                            value="4.5"
                            {{ request('rating') == '4.5' ? 'selected' : '' }}
                        >
                            {{ __('explore.and_above', ['rating' => '4.5']) }}
                        </option>

                        <option
                            value="4.0"
                            {{ request('rating') == '4.0' ? 'selected' : '' }}
                        >
                            {{ __('explore.and_above', ['rating' => '4.0']) }}
                        </option>

                        <option
                            value="3.5"
                            {{ request('rating') == '3.5' ? 'selected' : '' }}
                        >
                            {{ __('explore.and_above', ['rating' => '3.5']) }}
                        </option>

                        <option
                            value="3.0"
                            {{ request('rating') == '3.0' ? 'selected' : '' }}
                        >
                            {{ __('explore.and_above', ['rating' => '3.0']) }}
                        </option>

                    </select>

                </div>

            </div>

        </form>

        <script>
            (function () {
                var searchForm = document.querySelector('.search-panel form');
                var emptyError = document.getElementById('empty-search-error');
                function hasSearchSelection() {
                    return ['search', 'state_id', 'budget_level', 'rating'].some(function (name) {
                        return searchForm.elements[name].value.trim() !== '';
                    }) || !!searchForm.querySelector('input[name="categories[]"]:checked');
                }
                searchForm.addEventListener('submit', function (event) {
                    if (hasSearchSelection()) return;
                    event.preventDefault();
                    emptyError.hidden = false;
                    emptyError.focus();
                });
                ['input', 'change'].forEach(function (type) {
                    searchForm.addEventListener(type, function () {
                        if (hasSearchSelection()) emptyError.hidden = true;
                    });
                });
                function showPopupById(id) {
                    var popup = document.getElementById(id);
                    if (!popup || popup.dataset.popupShown === '1') {
                        return;
                    }
                    popup.dataset.popupShown = '1';
                    popup.classList.add('global-message-popup');
                    popup.setAttribute('role', 'status');
                    setTimeout(function () {
                        popup.classList.add('is-leaving');
                        setTimeout(function () { if (popup.parentNode) popup.remove(); }, 250);
                    }, 4000);
                }

                function showFilterSuccessPopup() {
                    showPopupById('searchSuccessPopup');
                    showPopupById('filterSuccessPopup');
                }

                function initClearFilters() {
                    var clearLink = document.getElementById('clearFiltersLink');
                    if (!clearLink) {
                        return;
                    }

                    clearLink.addEventListener('click', function (event) {
                        event.preventDefault();

                        var form = document.querySelector('.search-panel form') || clearLink.closest('form');
                        var liveSelected = false;

                        if (form) {
                            var searchInput = form.querySelector('input[name="search"]');
                            if (searchInput && String(searchInput.value).trim() !== '') {
                                liveSelected = true;
                            }

                            ['state_id', 'budget_level', 'rating'].forEach(function (name) {
                                var el = form.querySelector('[name="' + name + '"]');
                                if (el && String(el.value).trim() !== '') {
                                    liveSelected = true;
                                }
                            });

                            if (form.querySelectorAll('input[name="categories[]"]:checked').length > 0) {
                                liveSelected = true;
                            }
                        }

                        var submittedSelected = false;
                        try {
                            var params = new URLSearchParams(window.location.search);
                            ['search', 'state_id', 'budget_level', 'rating'].forEach(function (name) {
                                var v = params.get(name);
                                if (v !== null && String(v).trim() !== '') {
                                    submittedSelected = true;
                                }
                            });
                            params.forEach(function (value, key) {
                                if (key === 'categories' || key.indexOf('categories[') === 0) {
                                    if (String(value).trim() !== '') {
                                        submittedSelected = true;
                                    }
                                }
                            });
                        } catch (e) {
                            submittedSelected = false;
                        }

                        if (liveSelected || submittedSelected) {
                            window.location.href = "{!! route('attractions.index', ['clear' => '1', 'has_selection' => '1']) !!}";
                        } else {
                            window.location.href = "{!! route('attractions.index', ['clear' => '1']) !!}";
                        }
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function () {
                        showFilterSuccessPopup();
                        initClearFilters();
                    });
                } else {
                    showFilterSuccessPopup();
                    initClearFilters();
                }
            })();
        </script>

    </section>

    <section class="results-section">

        <div class="results-heading">

            <div>

                @if($searchSubmitted && request('search'))

                    <p class="results-kicker">
                        {{ __('explore.search_results') }}
                    </p>

                    <h2>
                        {{ __('explore.results_for', ['query' => request('search')]) }}
                    </h2>

                @elseif(!$searchSubmitted)

                    @if(isset($userPreferences) && $userPreferences->isNotEmpty())

                        <p class="results-kicker">
                            {{ __('explore.recommended') }}
                        </p>

                        <h2>
                            {{ __('explore.preferences') }}
                        </h2>

                        <div class="attraction-categories preference-tags">

                            @foreach($userPreferences as $preference)

                                <span class="attraction-category">
                                    {{ $preference->localized_name }}
                                </span>

                            @endforeach

                        </div>

                    @else

                        <p class="results-kicker">
                            {{ __('explore.popular') }}
                        </p>

                        <h2>
                            {{ __('explore.explore_malaysia') }}
                        </h2>

                    @endif

                @else

                    <p class="results-kicker">
                        {{ __('explore.search_results') }}
                    </p>

                    <h2>
                        {{ __('explore.explore_malaysia') }}
                    </h2>

                @endif

            </div>

            <span class="result-count">
                {{ $attractions->total() }}
                {{ $attractions->total() == 1 ? __('explore.place') : __('explore.places') }}
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
                                            {{ $preference->localized_name }}
                                        </span>

                                    @empty

                                        <span class="attraction-category">
                                            {{ __('explore.uncategorized') }}
                                        </span>

                                    @endforelse

                                </div>

                                <span class="budget-badge">
                                    {{ $attraction->budget_level ? __('explore.' . strtolower($attraction->budget_level)) : __('explore.price_unavailable') }}
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
                                    {{ __('explore.view') }}
                                    <span>→</span>
                                </a>

                                @if(in_array((int) $attraction->attraction_id, $wishlistedAttractionIds, true))

                                    <form method="POST" action="{{ route('attractions.wishlist.remove', $attraction->attraction_id) }}" data-ajax-crud data-ajax-wishlist>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="card-wishlist-button is-saved" aria-label="{{ __('explore.remove_wishlist', ['name' => $attraction->attraction_name]) }}">♥ {{ __('explore.saved') }}</button>
                                    </form>

                                @else

                                    <form method="POST" action="{{ route('attractions.wishlist.add', $attraction->attraction_id) }}" data-ajax-crud data-ajax-wishlist>
                                        @csrf
                                        <button type="submit" class="card-wishlist-button" aria-label="{{ __('explore.add_wishlist', ['name' => $attraction->attraction_name]) }}">♡ {{ __('explore.save') }}</button>
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
                    aria-label="{{ __('explore.pages') }}"
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
                        {{ __('explore.no_results') }}
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
