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
                    <p>Choose saved places for a trip, set your dates, then give the collection a name.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('saved-places.collections.store') }}" class="collection-form">
                @csrf
                <label for="collection-name">Collection name</label>
                <input id="collection-name" name="name" value="{{ old('name') }}" maxlength="80" placeholder="e.g. Langkawi weekend" required>
                @error('name')<p class="collection-error">{{ $message }}</p>@enderror

                <div class="collection-dates-row">
                    <div class="collection-date-field">
                        <label for="collection-start-date">Trip starts</label>
                        <input type="date" id="collection-start-date" name="start_date" value="{{ old('start_date') }}" min="{{ today()->format('Y-m-d') }}" class="{{ $errors->has('start_date') ? 'input-error' : '' }}" required>
                        @error('start_date')<p class="collection-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="collection-date-field">
                        <label for="collection-end-date">Trip ends</label>
                        <input type="date" id="collection-end-date" name="end_date" value="{{ old('end_date') }}" min="{{ old('start_date') ?: today()->format('Y-m-d') }}" class="{{ $errors->has('end_date') ? 'input-error' : '' }}" required>
                        @error('end_date')<p class="collection-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <label for="collection-places" id="collection-places-label">Select saved places</label>

                <div class="ms-select" data-fill-target="#collection-places">
                    <div
                        class="ms-fields"
                        role="combobox"
                        aria-expanded="false"
                        aria-controls="collection-places-options"
                        aria-labelledby="collection-places-label"
                    >
                        <div class="ms-chips"></div>
                        <input
                            id="collection-places-search"
                            class="ms-search"
                            type="text"
                            placeholder="Search places…"
                            autocomplete="off"
                            aria-label="Search saved places"
                        >
                        <span class="ms-chevron" aria-hidden="true"></span>
                    </div>

                    <ul
                        class="ms-listbox hidden"
                        id="collection-places-options"
                        role="listbox"
                        aria-labelledby="collection-places-label"
                    >
                        @foreach($savedPlaces as $place)
                            <li
                                class="ms-option"
                                role="option"
                                data-value="{{ $place->wishlist_id }}"
                                data-label="{{ $place->attraction->attraction_name }}"
                                aria-selected="false"
                                tabindex="-1"
                            >
                                {{ $place->attraction->attraction_name }}
                            </li>
                        @endforeach
                    </ul>

                    <select id="collection-places" name="wishlist_ids[]" multiple size="6" class="ms-native" hidden>
                        @foreach($savedPlaces as $place)
                            <option value="{{ $place->wishlist_id }}" {{ in_array($place->wishlist_id, old('wishlist_ids', [])) ? 'selected' : '' }}>
                                {{ $place->attraction->attraction_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @error('wishlist_ids')<p class="collection-error" data-error-for="wishlist_ids">{{ $message }}</p>@enderror

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
                                        @if($collection->start_date && $collection->end_date)
                                            <p class="collection-dates">
                                                📅 {{ \Carbon\Carbon::parse($collection->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($collection->end_date)->format('M d, Y') }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="collection-card-actions">
                                        @if($collection->items_count >= 2)
                                            <a href="{{ route('route.index', ['source' => 'saved', 'collection' => $collection->collection_id]) }}">Generate trip plan &rarr;</a>
                                        @endif
                                        <form method="POST" action="{{ route('saved-places.collections.destroy', $collection->collection_id) }}" class="collection-delete-form" onsubmit="return confirm('Delete this collection and all of its places?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="collection-delete-btn" aria-label="Delete collection">Delete</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="collection-attractions">
                                    @foreach($collection->items as $item)
                                        @if($item->wishlist && $item->wishlist->attraction)
                                            <div class="collection-attraction-item">
                                                <a href="{{ route('attractions.show', $item->wishlist->attraction->attraction_id) }}">
                                                    @if($item->wishlist->attraction->images->isNotEmpty())
                                                        <img src="{{ asset($item->wishlist->attraction->images->first()->image_path) }}" alt="">
                                                    @else
                                                        <span class="collection-image-fallback">ExploreMY</span>
                                                    @endif
                                                    <span>{{ $item->wishlist->attraction->attraction_name }}</span>
                                                </a>
                                                <form method="POST" action="{{ route('saved-places.collections.places.destroy', [$collection->collection_id, $item->wishlist_id]) }}" class="collection-remove-form" onsubmit="return confirm('Remove this place from the collection?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="collection-remove-btn" aria-label="Remove {{ $item->wishlist->attraction->attraction_name }}">&times;</button>
                                                </form>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                @php $collectionWishlistIds = $collection->items->pluck('wishlist_id')->all(); @endphp
                                @if($savedPlaces->whereNotIn('wishlist_id', $collectionWishlistIds)->isNotEmpty())
                                    <details class="add-to-collection">
                                        <summary>Add saved places</summary>
                                        <form method="POST" action="{{ route('saved-places.collections.places.store', $collection->collection_id) }}">
                                            @csrf
                                            <label for="add-places-{{ $collection->collection_id }}" id="add-places-label-{{ $collection->collection_id }}">Select places to add</label>
                                            <div class="ms-select" data-fill-target="#add-places-{{ $collection->collection_id }}">
                                                <div class="ms-fields" role="combobox" aria-expanded="false" aria-controls="add-places-opt-{{ $collection->collection_id }}" aria-labelledby="add-places-label-{{ $collection->collection_id }}">
                                                    <div class="ms-chips"></div>
                                                    <input class="ms-search" type="text" placeholder="Search places…" autocomplete="off" aria-label="Search saved places">
                                                    <span class="ms-chevron" aria-hidden="true"></span>
                                                </div>

                                                <ul class="ms-listbox hidden" id="add-places-opt-{{ $collection->collection_id }}" role="listbox" aria-labelledby="add-places-label-{{ $collection->collection_id }}">
                                                    @foreach($savedPlaces->whereNotIn('wishlist_id', $collectionWishlistIds) as $place)
                                                        <li class="ms-option" role="option" data-value="{{ $place->wishlist_id }}" data-label="{{ $place->attraction->attraction_name }}" aria-selected="false" tabindex="-1">
                                                            {{ $place->attraction->attraction_name }}
                                                        </li>
                                                    @endforeach
                                                </ul>

                                                <select id="add-places-{{ $collection->collection_id }}" name="wishlist_ids[]" multiple size="4" class="ms-native" hidden>
                                                    @foreach($savedPlaces->whereNotIn('wishlist_id', $collectionWishlistIds) as $place)
                                                        <option value="{{ $place->wishlist_id }}">{{ $place->attraction->attraction_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button type="submit">Add to collection</button>
                                        </form>
                                    </details>
                                @endif
                            </div>

                            @if($collection->items_count < 2)
                                <p class="collection-trip-note">
                                    Add {{ 2 - $collection->items_count }} {{ 2 - $collection->items_count === 1 ? 'more place' : 'places' }} to generate a trip.
                                </p>
                            @endif
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

<script>
    const collectionStartDate = document.getElementById('collection-start-date');
    const collectionEndDate = document.getElementById('collection-end-date');

    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const systemDate = `${year}-${month}-${day}`;

    if (collectionStartDate) {
        collectionStartDate.min = systemDate;
    }

    if (collectionEndDate) {
        collectionEndDate.min = systemDate;
    }

    if (collectionStartDate) {
        collectionStartDate.addEventListener('change', function () {
            if (this.value) {
                collectionEndDate.min = this.value;

                if (collectionEndDate.value && collectionEndDate.value < this.value) {
                    collectionEndDate.value = '';
                }
            } else {
                collectionEndDate.min = systemDate;
            }
        });
    }
</script>

<script>
    (function () {
        function initMultiSelect(root) {
            const fields = root.querySelector('.ms-fields');
            const search = root.querySelector('.ms-search');
            const chipsEl = root.querySelector('.ms-chips');
            const listbox = root.querySelector('.ms-listbox');
            const select = document.querySelector(root.dataset.fillTarget);
            const optionEls = Array.from(listbox.querySelectorAll('.ms-option'));

            let highlightedIndex = -1;

            const selectedValues = () => Array.from(select.selectedOptions).map(o => o.value);
            const optionByValue = new Map(optionEls.map(o => [String(o.dataset.value), o]));
            const visibleOptions = () => optionEls.filter(o => !o.classList.contains('hidden'));

            function renderChips() {
                chipsEl.innerHTML = '';
                selectedValues().forEach(value => {
                    const opt = optionByValue.get(String(value));
                    const label = opt ? opt.dataset.label : value;
                    const chip = document.createElement('span');
                    chip.className = 'ms-chip';

                    const text = document.createElement('span');
                    text.className = 'ms-field-text';
                    text.textContent = label;

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.setAttribute('aria-label', 'Remove ' + label);
                    removeBtn.innerHTML = '&times;';
                    removeBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        setValue(value, false);
                    });

                    chip.appendChild(text);
                    chip.appendChild(removeBtn);
                    chipsEl.appendChild(chip);
                });
            }

            function syncAria() {
                const selected = new Set(selectedValues());
                optionEls.forEach(o => {
                    o.setAttribute('aria-selected', selected.has(String(o.dataset.value)) ? 'true' : 'false');
                });
            }

            // Remove any "choose at least one saved place" error as soon as a
            // valid (non-empty) selection exists, so it never lingers on screen.
            function clearError() {
                if (selectedValues().length === 0) return;
                const form = root.closest('form');
                if (!form) return;
                form.querySelectorAll('.collection-error[data-error-for="wishlist_ids"]').forEach(el => el.remove());
            }

            function setValue(value, checked) {
                const option = select.querySelector('option[value="' + value + '"]');
                if (!option) return;
                option.selected = !!checked;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                renderChips();
                syncAria();
                clearError();
            }

            function removeEmpty() {
                const empty = listbox.querySelector('.ms-option.empty');
                if (empty) empty.remove();
            }

            function showEmpty() {
                removeEmpty();
                const empty = document.createElement('li');
                empty.className = 'ms-option empty';
                empty.setAttribute('role', 'presentation');
                empty.textContent = 'No saved places match your search.';
                listbox.insertBefore(empty, listbox.firstChild);
            }

function applyFilter() {
                const q = (search.value || '').toLowerCase().replace(/\s+/g, ' ').trim();
                const matches = [];

                optionEls.forEach(o => o.classList.add('hidden'));
                optionEls.forEach(o => {
                    const label = o.dataset.label.toLowerCase().replace(/\s+/g, ' ').trim();
                    if (!q || label.includes(q)) matches.push(o);
                });

                // Sort the matching places so the closest/best match appears at the top.
                if (q) {
                    matches.sort((a, b) => {
                        const al = a.dataset.label.toLowerCase();
                        const bl = b.dataset.label.toLowerCase();
                        const aStart = al.startsWith(q) ? 0 : 1;
                        const bStart = bl.startsWith(q) ? 0 : 1;
                        if (aStart !== bStart) return aStart - bStart;
                        return al.localeCompare(bl);
                    });
                }

                // Move the sorted matching places to the TOP of the dropdown, and
                // remove any lingering "no matches" placeholder so it can't float
                // above the real results.
                for (let i = matches.length - 1; i >= 0; i--) {
                    listbox.insertBefore(matches[i], listbox.firstChild);
                    matches[i].classList.remove('hidden');
                }

                if (q && matches.length === 0) showEmpty();
                else removeEmpty();
                highlightedIndex = -1;
                optionEls.forEach(o => o.classList.remove('highlighted'));
            }

            function open() {
                root.classList.add('open');
                listbox.classList.remove('hidden');
                fields.setAttribute('aria-expanded', 'true');
                applyFilter();
            }

            function close() {
                root.classList.remove('open');
                listbox.classList.add('hidden');
                fields.setAttribute('aria-expanded', 'false');
                search.value = '';
                removeEmpty();
                optionEls.forEach(o => o.classList.remove('hidden'));
                // Restore the original order once the search is closed.
                optionEls.forEach(o => listbox.appendChild(o));
                optionEls.forEach(o => o.classList.remove('highlighted'));
                highlightedIndex = -1;
            }

            function toggleFromEl(el) {
                const checked = el.getAttribute('aria-selected') !== 'true';
                setValue(el.dataset.value, checked);
            }

            fields.addEventListener('click', (e) => {
                if (e.target.closest('.ms-chip button')) return;
                open();
                search.focus();
            });

            search.addEventListener('focus', open);
            search.addEventListener('input', applyFilter);

            search.addEventListener('keydown', (e) => {
                const items = visibleOptions();
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (!items.length) return;
                    const delta = e.key === 'ArrowDown' ? 1 : -1;
                    highlightedIndex = (highlightedIndex + delta + items.length) % items.length;
                    items.forEach((o, i) => o.classList.toggle('highlighted', i === highlightedIndex));
                    items[highlightedIndex].scrollIntoView({ block: 'nearest' });
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const q = (search.value || '').toLowerCase().replace(/\s+/g, ' ').trim();
                    // Auto-select only when the typed text is the FULL (or near-exact) place name.
                    // Partial / similar names are NOT auto-added — the user must click a suggestion.
                    const exact = items.find(o => o.dataset.label.toLowerCase().replace(/\s+/g, ' ').trim() === q);
                    if (exact) {
                        toggleFromEl(exact);
                        search.value = '';
                        applyFilter();
                    } else if (highlightedIndex >= 0 && items[highlightedIndex]) {
                        // User arrow-selected a suggestion -> commit it explicitly.
                        toggleFromEl(items[highlightedIndex]);
                        search.value = '';
                        applyFilter();
                    }
                    // Otherwise stay open so the user can click one of the matching places.
                } else if (e.key === 'Backspace' && !search.value) {
                    const selected = selectedValues();
                    if (selected.length) setValue(selected[selected.length - 1], false);
                } else if (e.key === 'Escape') {
                    close();
                    search.blur();
                }
            });

            optionEls.forEach(o => {
                o.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    toggleFromEl(o);
                });
            });

            document.addEventListener('click', (e) => {
                if (!root.contains(e.target)) close();
            });

            renderChips();
            syncAria();
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.ms-select').forEach(initMultiSelect);
        });
    })();
</script>

</body>
</html>