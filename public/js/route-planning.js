document.addEventListener('DOMContentLoaded', () => {
    const translations = window.routeTranslations || {};
    const options = document.querySelectorAll(
        '.preference-option'
    );

    options.forEach(option => {
        const radio = option.querySelector(
            'input[type="radio"]'
        );

        radio.addEventListener('change', () => {
            options.forEach(item => {
                item.classList.remove('selected');
            });

            option.classList.add('selected');
        });
    });

    const editor = document.querySelector('[data-itinerary-editor]');
    if (!editor) {
        return;
    }

    const list = editor.querySelector('[data-itinerary-list]');
    const destinationSelect = editor.querySelector('[data-add-destination]');
    const addButton = editor.querySelector('[data-add-stop]');
    const savedPlaceSearch = editor.querySelector('[data-saved-place-search]');
    const savedPlaceSearchStatus = editor.querySelector('[data-saved-search-status]');
    const savedPlaceLoadMore = editor.querySelector('[data-saved-place-load-more]');
    const countLabel = editor.querySelector('[data-destination-count]');
    const hint = editor.querySelector('[data-itinerary-hint]');
    const form = editor.closest('form');
    const minimumDestinations = 2;
    const maximumDestinations = 8;
    let savedPlaceSearchPage = 1;
    let savedPlaceSearchHasMore = true;
    let savedPlaceSearchTimer = null;
    let savedPlaceSearchController = null;

    const updateEditor = () => {
        const items = [...list.querySelectorAll('.itinerary-item')];
        const selectedKeys = new Set();

        items.forEach((item, index) => {
            item.querySelector('.itinerary-position').textContent = String(index + 1);
            selectedKeys.add(item.dataset.destinationKey);
        });

        [...destinationSelect.options].forEach(option => {
            option.disabled = selectedKeys.has(option.value);
        });

        countLabel.textContent = (translations.stops || ':count stops').replace(':count', items.length);
        const isValid = items.length >= minimumDestinations;
        hint.hidden = isValid;
        form.querySelector('.continue-button').disabled = !isValid;
        addButton.disabled = items.length >= maximumDestinations;
    };

    const makeItem = (key, name) => {
        const item = document.createElement('li');
        item.className = 'itinerary-item';
        item.dataset.destinationKey = key;
        item.innerHTML = `
            <input type="hidden" name="destination_keys[]" value="${key}">
            <span class="itinerary-position"></span>
            <strong></strong>
            <div class="itinerary-actions">
                <button type="button" data-move="up" aria-label="${translations.moveUp || 'Move destination up'}">&uarr;</button>
                <button type="button" data-move="down" aria-label="${translations.moveDown || 'Move destination down'}">&darr;</button>
                <button type="button" data-remove aria-label="${translations.remove || 'Remove destination'}">&times;</button>
            </div>`;
        item.querySelector('strong').textContent = name;
        return item;
    };

    const loadSavedPlaces = async (page = 1, append = false) => {
        if (!savedPlaceSearch) return;

        savedPlaceSearchController?.abort();
        savedPlaceSearchController = new AbortController();
        const url = new URL(savedPlaceSearch.dataset.searchUrl, window.location.origin);
        const query = savedPlaceSearch.value.trim();
        const collectionId = savedPlaceSearch.dataset.collectionId;
        url.searchParams.set('page', String(page));
        if (query) url.searchParams.set('q', query);
        if (collectionId) url.searchParams.set('collection_id', collectionId);

        savedPlaceSearchStatus.textContent = 'Searching...';
        savedPlaceLoadMore.disabled = true;

        try {
            const response = await fetch(url, {
                headers: {'Accept': 'application/json'},
                signal: savedPlaceSearchController.signal,
            });
            if (!response.ok) throw new Error('Saved-place search failed.');
            const result = await response.json();

            if (!append) {
                destinationSelect.replaceChildren(new Option(
                    translations.choosePlace || 'Choose a place',
                    ''
                ));
            }

            result.data.forEach(place => {
                if ([...destinationSelect.options].some(option => option.value === place.route_key)) return;
                const details = [place.category, place.state].filter(Boolean).join(' · ');
                const option = new Option(details ? `${place.name} — ${details}` : place.name, place.route_key);
                option.dataset.name = place.name;
                destinationSelect.add(option);
            });

            savedPlaceSearchPage = result.current_page;
            savedPlaceSearchHasMore = result.has_more;
            savedPlaceLoadMore.hidden = !savedPlaceSearchHasMore;
            savedPlaceLoadMore.disabled = false;
            savedPlaceSearchStatus.textContent = result.data.length
                ? `${destinationSelect.options.length - 1} matching places loaded`
                : 'No saved places found';
            updateEditor();
        } catch (error) {
            if (error.name === 'AbortError') return;
            savedPlaceSearchStatus.textContent = 'Could not load saved places. Please try again.';
            savedPlaceLoadMore.disabled = false;
        }
    };

    if (savedPlaceSearch) {
        savedPlaceSearch.addEventListener('input', () => {
            clearTimeout(savedPlaceSearchTimer);
            savedPlaceSearchTimer = setTimeout(() => loadSavedPlaces(1, false), 300);
        });
        savedPlaceLoadMore.addEventListener('click', () => {
            if (savedPlaceSearchHasMore) loadSavedPlaces(savedPlaceSearchPage + 1, true);
        });
    }

    addButton.addEventListener('click', () => {
        if (list.querySelectorAll('.itinerary-item').length >= maximumDestinations) {
            return;
        }

        const option = destinationSelect.selectedOptions[0];
        if (!option || !option.value) {
            return;
        }

        list.append(makeItem(option.value, option.dataset.name));
        destinationSelect.value = '';
        updateEditor();
    });

    list.addEventListener('click', event => {
        const button = event.target.closest('button');
        const item = button?.closest('.itinerary-item');
        if (!button || !item) {
            return;
        }

        if (button.hasAttribute('data-remove')) {
            item.remove();
        } else if (button.dataset.move === 'up' && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
        } else if (button.dataset.move === 'down' && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
        }

        updateEditor();
    });

    updateEditor();

    const guidanceModals = document.querySelectorAll('[data-guidance-modal]');
    const rewardEvent = button => {
        const token = document.querySelector('input[name="_token"]')?.value;
        return fetch(button.dataset.rewardUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
            },
            body: JSON.stringify({activity: button.dataset.rewardActivity}),
        });
    };

    document.querySelectorAll('[data-reward-activity="export_itinerary"]').forEach(button => {
        button.addEventListener('click', () => {
            rewardEvent(button);
            window.print();
        });
    });

    document.querySelectorAll('[data-reward-activity="export_guidance"]').forEach(button => {
        button.addEventListener('click', () => {
            rewardEvent(button);
            window.print();
        });
    });

    document.querySelectorAll('[data-reward-activity="share_itinerary"]').forEach(button => {
        button.addEventListener('click', async () => {
            try {
                if (navigator.share) {
                    await navigator.share({title: document.title, text: translations.shareText || 'Explore my Malaysia itinerary.'});
                } else {
                    await navigator.clipboard.writeText(window.location.href);
                }
                rewardEvent(button);
            } catch (error) {
                if (error.name !== 'AbortError') console.error(error);
            }
        });
    });

    const closeGuidance = modal => {
        modal.hidden = true;
    };

    document.querySelectorAll('[data-guidance-open]').forEach(button => {
        button.addEventListener('click', () => {
            const modal = document.getElementById(button.dataset.guidanceOpen);
            if (modal) {
                modal.hidden = false;
                modal.querySelector('[data-guidance-close]').focus();
            }
        });
    });

    guidanceModals.forEach(modal => {
        modal.querySelector('[data-guidance-close]').addEventListener('click', () => {
            closeGuidance(modal);
        });

        modal.addEventListener('click', event => {
            if (event.target === modal) {
                closeGuidance(modal);
            }
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            guidanceModals.forEach(closeGuidance);
        }
    });
});

window.initRouteMap = () => {
    const mapElement = document.getElementById('route-map');
    const dataElement = document.getElementById('route-map-data');
    const route = dataElement ? JSON.parse(dataElement.textContent) : null;

    if (!mapElement || !route || !window.google) {
        return;
    }

    const firstLocatedStop = route.stops.find(
        stop => stop.latitude !== null && stop.longitude !== null
    );
    const map = new google.maps.Map(mapElement, {
        center: firstLocatedStop ? {
            lat: firstLocatedStop.latitude,
            lng: firstLocatedStop.longitude,
        } : {lat: 4.2105, lng: 101.9758},
        zoom: 14,
        mapTypeControl: false,
        streetViewControl: false,
    });
    const bounds = new google.maps.LatLngBounds();
    const colours = ['#1565c0', '#7b1fa2', '#00897b', '#ef6c00', '#c62828'];

    route.stops.forEach((stop, index) => {
        if (stop.latitude === null || stop.longitude === null) {
            return;
        }

        const position = {lat: stop.latitude, lng: stop.longitude};
        bounds.extend(position);

        new google.maps.Marker({
            map,
            position,
            label: String(index + 1),
            title: stop.name,
        });
    });

    route.transit_legs.forEach((leg, index) => {
        leg.encoded_polylines.forEach(encodedPolyline => {
            const path = google.maps.geometry.encoding.decodePath(
                encodedPolyline
            );

            path.forEach(point => bounds.extend(point));

            new google.maps.Polyline({
                map,
                path,
                strokeColor: colours[index % colours.length],
                strokeOpacity: 0.9,
                strokeWeight: 5,
            });
        });
    });

    map.fitBounds(bounds, 40);
};
