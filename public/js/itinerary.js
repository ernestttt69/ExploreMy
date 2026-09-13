(function () {
    'use strict';

    const config = window.itineraryConfig;

    if (!config) {
        return;
    }

    const icons = {
        transport: '⌁',
        lodging: '⌂',
        activity: '◌',
        food: '⌑',
        sightseeing: '⌘'
    };
    const translations = config.translations || {};
    const t = (key, replacements = {}) => Object.entries(replacements).reduce(
        (message, [name, value]) => message.replace(`:${name}`, value),
        translations[key] || key
    );
    const labels = Object.fromEntries(['transport', 'lodging', 'activity', 'food', 'sightseeing'].map((key) => [key, t(key)]));
    const cacheKey = `exploremy:itinerary:${config.itinerary.trip_id}`;
    let state = clone(config.itinerary);
    let deletedIds = [];
    let offlineDirty = false;
    let currentView = 'timeline';
    let leafletMap = null;
    let leafletLayer = null;
    let draggedId = null;
    let weatherByDate = {};
    let weatherByStopId = {};

    const itemsElement = document.getElementById('itinerary-items');
    const mapElement = document.getElementById('map-view');
    const syncBanner = document.getElementById('sync-banner');
    const itemDialog = document.getElementById('item-dialog');
    const itemForm = document.getElementById('item-form');
    const shareDialog = document.getElementById('share-dialog');
    const shareForm = document.getElementById('share-form');

    hydrateFromCache();
    bindEvents();
    render();
    loadWeather();

    if (offlineDirty && navigator.onLine && config.syncUrl) {
        syncOfflineEdits();
    }

    /**
     * Bind every interactive control once after the initial page render.
     */
    function bindEvents() {
        document.querySelectorAll('[data-view]').forEach((button) => {
            button.addEventListener('click', () => {
                currentView = button.dataset.view;
                render();
            });
        });

        document.addEventListener('click', (event) => {
            const actionElement = event.target.closest('[data-action]');

            if (!actionElement) {
                return;
            }

            const action = actionElement.dataset.action;

            if (action === 'open-item-dialog') {
                openItemDialog();
            } else if (action === 'edit-item') {
                openItemDialog(findItem(actionElement.dataset.itemId));
            } else if (action === 'delete-item') {
                deleteItem(actionElement.dataset.itemId);
            } else if (action === 'apply-eco') {
                applyEcoAlternative(actionElement.dataset.itemId);
            } else if (action === 'close-item-dialog') {
                itemDialog.close();
            } else if (action === 'save') {
                saveItinerary();
            } else if (action === 'toggle-export') {
                const popover = document.getElementById('export-popover');
                popover.hidden = !popover.hidden;
            } else if (action === 'open-share-dialog') {
                shareDialog.showModal();
            } else if (action === 'close-share-dialog') {
                shareDialog.close();
            } else if (action === 'copy-share') {
                copyShareLink();
            } else if (action === 'refresh-weather') {
                loadWeather(true);
            }
        });

        document.addEventListener('click', (event) => {
            const popover = document.getElementById('export-popover');

            if (popover && !popover.hidden && !event.target.closest('.export-menu')) {
                popover.hidden = true;
            }
        });

        if (itemForm) {
            itemForm.addEventListener('submit', submitItem);
            document.getElementById('item-category').addEventListener('change', toggleTransportFields);
        }

        if (shareForm) {
            shareForm.addEventListener('submit', createShareLink);
        }

        document.querySelectorAll('[data-reward-export]').forEach((link) => {
            link.addEventListener('click', showRewardDot);
        });

        window.addEventListener('online', () => {
            showBanner(t('online'));
            syncOfflineEdits();
        });

        window.addEventListener('offline', () => {
            showBanner(t('offline'), true);
        });
    }

    /**
     * Render the active layout and every live footprint summary.
     */
    function render() {
        const isMap = currentView === 'map';

        document.querySelectorAll('[data-view]').forEach((button) => {
            const selected = button.dataset.view === currentView;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-selected', String(selected));
        });

        document.getElementById('view-summary').textContent = summaryText();
        itemsElement.hidden = isMap;
        mapElement.hidden = !isMap;

        if (isMap) {
            renderMap();
        } else {
            renderItems();
        }

        renderCarbonDashboard();
    }

    /**
     * Render a chronological timeline or a day-grouped agenda.
     */
    function renderItems() {
        if (!state.items.length) {
            itemsElement.innerHTML = emptyStateMarkup();
            return;
        }

        const groups = groupItemsByDate(sortedItems());
        itemsElement.innerHTML = Object.entries(groups).map(([date, items], index) => dayGroupMarkup(date, items, index + 1)).join('');
        bindItemCardEvents();
    }

    /**
     * Build the empty state shown before a traveller adds their first item.
     */
    function emptyStateMarkup() {
        const action = config.canEdit
            ? `<button class="button button-primary" type="button" data-action="open-item-dialog">${escapeHtml(t('add_activity'))}</button>`
            : '';

        return `<div class="empty-itinerary card-surface">
            <div class="empty-illustration" aria-hidden="true">✦</div>
            <h2>${escapeHtml(t('empty_title'))}</h2>
            <p>${escapeHtml(t('empty_text'))}</p>
            ${action}
        </div>`;
    }

    /**
     * Create one date group with agenda/timeline cards.
     */
    function dayGroupMarkup(date, items, tripDayNumber) {
        const formatted = dateLabel(date);
        const rail = currentView === 'timeline'
            ? `<div class="day-rail"><span class="day-month">${escapeHtml(formatted.month)}</span><span class="day-number">${escapeHtml(formatted.day)}</span><span class="day-year">${escapeHtml(formatted.year)}</span></div>`
            : `<div class="day-rail"><span class="day-month">${escapeHtml(t('day'))}</span><span class="day-number">${escapeHtml(String(tripDayNumber))}</span></div>`;
        const description = currentView === 'agenda' ? `${formatted.weekday}, ${formatted.month} ${formatted.day}` : `${items.length} ${t(items.length === 1 ? 'item' : 'items')}`;

        return `<section class="day-group" data-date="${escapeAttribute(date)}">
            ${rail}
            <div class="day-content">
                <div class="day-heading"><span>${escapeHtml(description)}</span><span>${escapeHtml(date === 'unscheduled' ? t('flexible_date') : '')}</span></div>
                ${weatherMarkup(date)}
                ${items.map(itemCardMarkup).join('')}
            </div>
        </section>`;
    }

    /**
     * Render a rich card for one itinerary item.
     */
    function itemCardMarkup(item) {
        const time = item.start_time ? `${formatTime(item.start_time)}${item.end_time ? ` - ${formatTime(item.end_time)}` : ''}` : 'Any time';
        const location = item.location ? `<p class="item-location">⌖ ${escapeHtml(item.location)}</p>` : '';
        const notes = item.notes ? `<p class="item-notes">${escapeHtml(item.notes)}</p>` : '';
        const transport = item.category === 'transport' && item.transport_label !== 'Not specified'
            ? `<span class="metric-tag is-neutral">${escapeHtml(item.transport_label)}${item.distance_km ? ` · ${escapeHtml(String(item.distance_km))} km` : ''}</span>`
            : '';
        const controls = config.canEdit
            ? `<div class="item-card-actions">
                <button class="mini-action" type="button" data-action="edit-item" data-item-id="${item.item_id}">${escapeHtml(t('edit'))}</button>
                <button class="mini-action is-danger" type="button" data-action="delete-item" data-item-id="${item.item_id}">${escapeHtml(t('delete'))}</button>
            </div>`
            : '';
        const placeWeather = weatherForItem(item);
        const ecoSuggestion = item.eco_suggestion && config.canEdit
            ? `<div class="suggestion-card"><p><strong>${escapeHtml(t('lower_carbon'))}</strong> ${escapeHtml(t('choose'))} ${escapeHtml(item.eco_suggestion.label)} ${escapeHtml(t('and_save'))} ${number(item.eco_suggestion.saving_kg)} ${escapeHtml(t('kg'))} ${escapeHtml(item.eco_suggestion.reason)}</p><button type="button" class="button button-outline" data-action="apply-eco" data-item-id="${item.item_id}">${escapeHtml(t('accept'))}</button></div>`
            : '';
        const draggable = canReorder() ? 'draggable="true"' : '';

        return `<article class="item-card" data-item-id="${item.item_id}" ${draggable}>
            <div class="item-icon" aria-hidden="true">${icons[item.category] || '•'}</div>
            <div>
                <div class="item-card-top">
                    <div class="item-title-row"><h3 class="item-title">${escapeHtml(item.title)}</h3>${placeWeather}</div>
                    <span class="item-time">${escapeHtml(time)}</span>
                    ${controls}
                </div>
                ${location}
                <div class="item-meta">
                    <span class="metric-tag">${escapeHtml(labels[item.category] || 'Item')}</span>
                    <span class="metric-tag">${number(item.carbon_kg)} kg CO<sub>2</sub>e</span>
                    ${transport}
                </div>
                ${notes}
                ${ecoSuggestion}
            </div>
        </article>`;
    }

    /**
     * Bind drag-and-drop events after new item cards are inserted.
     */
    function bindItemCardEvents() {
        if (!canReorder()) {
            return;
        }

        itemsElement.querySelectorAll('.item-card').forEach((card) => {
            card.addEventListener('dragstart', () => {
                draggedId = String(card.dataset.itemId);
                card.classList.add('is-dragging');
            });
            card.addEventListener('dragend', () => {
                draggedId = null;
                card.classList.remove('is-dragging');
                document.querySelectorAll('.is-drop-target').forEach((target) => target.classList.remove('is-drop-target'));
            });
            card.addEventListener('dragover', (event) => {
                event.preventDefault();
                if (String(card.dataset.itemId) !== draggedId) {
                    card.classList.add('is-drop-target');
                }
            });
            card.addEventListener('dragleave', () => card.classList.remove('is-drop-target'));
            card.addEventListener('drop', (event) => {
                event.preventDefault();
                card.classList.remove('is-drop-target');
                reorderItems(draggedId, String(card.dataset.itemId));
            });
        });
    }

    /**
     * Move the dragged item before its destination and persist its order.
     */
    function reorderItems(sourceId, destinationId) {
        if (!sourceId || sourceId === destinationId) {
            return;
        }

        const ordered = sortedItems();
        const sourceIndex = ordered.findIndex((item) => String(item.item_id) === sourceId);
        const destinationIndex = ordered.findIndex((item) => String(item.item_id) === destinationId);

        if (sourceIndex < 0 || destinationIndex < 0) {
            return;
        }

        const [item] = ordered.splice(sourceIndex, 1);
        ordered.splice(destinationIndex, 0, item);
        state.items = ordered;
        state.items.forEach((entry, index) => { entry.sort_order = index; entry.updated_at = new Date().toISOString(); });
        cacheCurrentState();
        render();

        if (!navigator.onLine) {
            markOfflineChange('Item order saved on this device.');
            return;
        }

        request(config.reorderUrl, 'POST', { items: state.items.filter(isServerItem).map((entry) => ({ item_id: entry.item_id, sort_order: entry.sort_order })) })
            .then((payload) => applyPayload(payload.itinerary, payload.message))
            .catch(handleRequestFailure);
    }

    /**
     * Show and populate the add/edit item dialog.
     */
    function openItemDialog(item) {
        if (!itemDialog || !config.canEdit) {
            return;
        }

        itemForm.reset();
        document.getElementById('item-id').value = item ? item.item_id : '';
        document.getElementById('item-dialog-title').textContent = item ? t('edit_item') : t('add_item');
        document.getElementById('item-submit').textContent = item ? t('save_changes') : t('add_item');
        document.getElementById('item-category').value = item ? item.category : 'activity';
        document.getElementById('item-title').value = item ? item.title : '';
        document.getElementById('item-date').value = item ? item.scheduled_date || '' : state.start_date || '';
        document.getElementById('item-start-time').value = item ? item.start_time || '' : '';
        document.getElementById('item-end-time').value = item ? item.end_time || '' : '';
        document.getElementById('item-location').value = item ? item.location || '' : '';
        document.getElementById('item-transport-mode').value = item ? item.transport_mode || '' : '';
        document.getElementById('item-distance').value = item && item.distance_km !== null ? item.distance_km : '';
        document.getElementById('item-notes').value = item ? item.notes || '' : '';
        document.getElementById('item-latitude').value = item && item.latitude !== null ? item.latitude : '';
        document.getElementById('item-longitude').value = item && item.longitude !== null ? item.longitude : '';
        toggleTransportFields();
        itemDialog.showModal();
    }

    /**
     * Show transport-specific inputs only for transport itinerary items.
     */
    function toggleTransportFields() {
        const fields = document.getElementById('transport-fields');
        const isTransport = document.getElementById('item-category').value === 'transport';
        fields.hidden = !isTransport;
    }

    /**
     * Submit an add/edit mutation, caching it locally if the network is unavailable.
     */
    function submitItem(event) {
        event.preventDefault();
        const raw = Object.fromEntries(new FormData(itemForm).entries());
        const itemId = raw.item_id;
        delete raw.item_id;

        if (!raw.scheduled_date) {
            raw.scheduled_date = null;
        }

        if (!navigator.onLine) {
            saveItemLocally(itemId, raw);
            itemDialog.close();
            return;
        }

        const url = itemId ? itemUrl(itemId) : config.itemStoreUrl;
        request(url, itemId ? 'PUT' : 'POST', raw)
            .then((payload) => {
                applyPayload(payload.itinerary, payload.message);
                itemDialog.close();
            })
            .catch((error) => {
                if (error.network) {
                    saveItemLocally(itemId, raw);
                    itemDialog.close();
                    return;
                }

                showBanner(error.message || t('save_failed'), true);
            });
    }

    /**
     * Update the locally cached representation for an offline add or edit.
     */
    function saveItemLocally(itemId, raw) {
        const existing = itemId ? findItem(itemId) : null;
        const transportMode = raw.category === 'transport' ? raw.transport_mode || null : null;
        const distance = transportMode ? Number(raw.distance_km || 0) : null;
        const item = {
            ...(existing || {}),
            ...raw,
            item_id: existing ? existing.item_id : `local-${Date.now()}`,
            distance_km: distance,
            latitude: raw.latitude === '' ? null : Number(raw.latitude),
            longitude: raw.longitude === '' ? null : Number(raw.longitude),
            carbon_kg: estimateCarbon(transportMode, distance || 0),
            transport_mode: transportMode,
            transport_label: transportLabel(transportMode),
            eco_suggestion: ecoSuggestion(transportMode, distance || 0),
            sort_order: existing ? existing.sort_order : state.items.length,
            updated_at: new Date().toISOString()
        };

        if (existing) {
            state.items = state.items.map((entry) => String(entry.item_id) === String(itemId) ? item : entry);
        } else {
            state.items.push(item);
        }

        markOfflineChange('Item saved on this device and queued for cloud sync.');
        render();
    }

    /**
     * Delete an item, or record its deletion for the next online sync.
     */
    function deleteItem(itemId) {
        const item = findItem(itemId);

        if (!item || !window.confirm(t('remove_confirm', { title: item.title }))) {
            return;
        }

        if (!navigator.onLine) {
            deleteItemLocally(itemId);
            return;
        }

        request(deleteUrl(itemId), 'DELETE')
            .then((payload) => applyPayload(payload.itinerary, payload.message))
            .catch((error) => {
                if (error.network) {
                    deleteItemLocally(itemId);
                    return;
                }

                showBanner(error.message || t('delete_failed'), true);
            });
    }

    /**
     * Apply a local deletion and remember server items that need removal later.
     */
    function deleteItemLocally(itemId) {
        if (isServerItem({ item_id: itemId })) {
            deletedIds.push(Number(itemId));
        }

        state.items = state.items.filter((item) => String(item.item_id) !== String(itemId));
        markOfflineChange('Item removed on this device and queued for cloud sync.');
        render();
    }

    /**
     * Accept an eco alternative and immediately recalculate the total.
     */
    function applyEcoAlternative(itemId) {
        const item = findItem(itemId);

        if (!item || !item.eco_suggestion) {
            return;
        }

        if (!navigator.onLine) {
            applyEcoLocally(item);
            return;
        }

        request(ecoUrl(itemId), 'POST')
            .then((payload) => applyPayload(payload.itinerary, payload.message))
            .catch((error) => {
                if (error.network) {
                    applyEcoLocally(item);
                    return;
                }

                showBanner(error.message || t('alternative_failed'), true);
            });
    }

    /**
     * Apply an eco alternative in local cache while offline.
     */
    function applyEcoLocally(item) {
        const alternative = item.eco_suggestion;
        item.transport_mode = alternative.mode;
        item.transport_label = alternative.label;
        item.carbon_kg = alternative.alternative_carbon_kg;
        item.eco_note = `Eco alternative applied: ${alternative.label}.`;
        item.eco_suggestion = null;
        item.updated_at = new Date().toISOString();
        markOfflineChange('Lower-carbon alternative applied on this device.');
        render();
    }

    /**
     * Confirm a manual save or explain that the current cached snapshot is safe offline.
     */
    function saveItinerary() {
        if (!navigator.onLine) {
            markOfflineChange(t('saved_locally'));
            return;
        }

        if (offlineDirty) {
            syncOfflineEdits();
            return;
        }

        request(config.saveUrl, 'POST')
            .then((payload) => applyPayload(payload.itinerary, payload.message))
            .catch(handleRequestFailure);
    }

    /**
     * Send the local snapshot to the cloud and let the server protect newer cloud edits.
     */
    function syncOfflineEdits() {
        if (!config.syncUrl || !offlineDirty || !navigator.onLine) {
            return;
        }

        request(config.syncUrl, 'POST', { items: state.items, deleted_ids: deletedIds })
            .then((payload) => {
                offlineDirty = false;
                deletedIds = [];
                applyPayload(payload.itinerary, payload.message);

                if (payload.conflicts && payload.conflicts.length) {
                    showBanner(`${payload.message} ${payload.conflicts.length} conflict(s) kept the newer cloud version.`, true);
                }
            })
            .catch(handleRequestFailure);
    }

    /**
     * Generate a share link and expose it for copying.
     */
    function createShareLink(event) {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(shareForm).entries());

        request(config.shareUrl, 'POST', data)
            .then((payload) => {
                const result = document.getElementById('share-result');
                document.getElementById('share-url').value = payload.url;
                result.hidden = false;
                showBanner(payload.message);
                if (payload.reward_queued) {
                    showRewardDot();
                }
            })
            .catch(handleRequestFailure);
    }

    /**
     * Copy the newly generated secure share link.
     */
    function copyShareLink() {
        const input = document.getElementById('share-url');

        if (!input.value) {
            return;
        }

        navigator.clipboard?.writeText(input.value)
            .then(() => showBanner(t('copied')))
            .catch(() => {
                input.select();
                document.execCommand('copy');
                showBanner(t('copied'));
            });
    }

    /**
     * Render an interactive Leaflet map when available.
     */
    function renderMap() {
        if (!window.L) {
            mapElement.innerHTML = `<p class="map-help">${escapeHtml(t('map_offline'))}</p>`;
            return;
        }

        if (!leafletMap) {
            leafletMap = window.L.map('itinerary-map', { scrollWheelZoom: false });
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(leafletMap);
        }

        if (leafletLayer) {
            leafletLayer.remove();
        }

        leafletLayer = window.L.featureGroup().addTo(leafletMap);
        const points = state.items.filter(hasValidCoordinates);

        points.forEach((item) => {
            window.L.marker([Number(item.latitude), Number(item.longitude)])
                .bindPopup(`<strong>${escapeHtml(item.title)}</strong><br>${escapeHtml(item.location || t('itinerary_stop'))}`)
                .addTo(leafletLayer);
        });

        if (points.length > 1) {
            window.L.polyline(points.map((item) => [Number(item.latitude), Number(item.longitude)]), { color: '#237954', weight: 4 }).addTo(leafletLayer);
            leafletMap.fitBounds(leafletLayer.getBounds().pad(0.2));
        } else if (points.length === 1) {
            leafletMap.setView([Number(points[0].latitude), Number(points[0].longitude)], 12);
        } else {
            leafletMap.setView(state.map_center || [3.1390, 101.6869], 6);
        }

        window.setTimeout(() => leafletMap.invalidateSize(), 10);
    }

    function showRewardDot() {
        document.querySelector('[data-reward-dot]')?.removeAttribute('hidden');
    }

    /**
     * Null coordinates must not be converted to zero: Number(null) is 0,0,
     * which would incorrectly place transport entries in the Gulf of Guinea.
     */
    function hasValidCoordinates(item) {
        if (item.latitude === null || item.latitude === '' || item.longitude === null || item.longitude === '') {
            return false;
        }

        const latitude = Number(item.latitude);
        const longitude = Number(item.longitude);

        return Number.isFinite(latitude)
            && Number.isFinite(longitude)
            && latitude >= -90
            && latitude <= 90
            && longitude >= -180
            && longitude <= 180
            && !(latitude === 0 && longitude === 0);
    }

    /**
     * Render the live total and category breakdown from the current state.
     */
    function renderCarbonDashboard() {
        const total = state.items.reduce((sum, item) => sum + Number(item.carbon_kg || 0), 0);
        const breakdown = state.items.reduce((all, item) => {
            all[item.category] = (all[item.category] || 0) + Number(item.carbon_kg || 0);
            return all;
        }, {});
        const totalElement = document.getElementById('carbon-total');
        const barElement = document.getElementById('carbon-bar');
        const breakdownElement = document.getElementById('carbon-breakdown');

        totalElement.textContent = number(total);
        barElement.style.width = `${Math.min(100, total ? 20 + Math.min(total, 250) / 3.125 : 0)}%`;
        breakdownElement.innerHTML = Object.keys(breakdown).length
            ? Object.entries(breakdown).sort(([, a], [, b]) => b - a).map(([category, value]) => `<div class="breakdown-row"><span>${escapeHtml(labels[category] || category)}</span><strong>${number(value)} kg</strong></div>`).join('')
            : `<div class="breakdown-row"><span>${escapeHtml(t('empty_carbon'))}</span></div>`;
    }

    /**
     * Load weather for all geocoded itinerary stops without blocking itinerary use.
     */
    function loadWeather(refresh) {
        if (!config.weatherUrl || !navigator.onLine) {
            return;
        }

        const separator = config.weatherUrl.includes('?') ? '&' : '?';
        const url = refresh ? `${config.weatherUrl}${separator}refresh=1` : config.weatherUrl;

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then((payload) => {
                weatherByDate = payload.stops.reduce((forecastGroups, stop) => {
                    const date = stop.scheduled_date;
                    forecastGroups[date] = forecastGroups[date] || [];
                    forecastGroups[date].push(stop);
                    return forecastGroups;
                }, {});
                weatherByStopId = payload.stops.reduce((stops, stop) => {
                    stops[String(stop.stop_id)] = stop;
                    return stops;
                }, {});
                render();

                if (refresh) {
                    const unavailable = payload.stops.filter((stop) => !stop.weather.available).length;
                    const stale = payload.stops.filter((stop) => stop.weather.is_stale).length;
                    showBanner(unavailable
                        ? t('weather_unavailable_stops')
                        : stale
                            ? t('weather_cached')
                            : t('weather_refreshed'), unavailable > 0 || stale > 0);
                }
            })
            .catch(() => {
                showBanner(t('weather_load_failed'), true);
            });
    }

    /**
     * Build the daily weather widget and any outdoor-activity warning.
     */
    function weatherMarkup(date) {
        const stops = weatherByDate[date];

        if (!stops || !stops.length) {
            return '';
        }

        const forecast = stops.find((stop) => stop.weather.available)?.weather || stops[0].weather;

        if (!forecast.available) {
            return `<div class="weather-widget weather-unavailable">${escapeHtml(t('weather_date_unavailable'))}</div>`;
        }

        const alerts = stops.filter((stop) => stop.requires_weather_alert);
        const estimate = forecast.is_historical_estimate ? `<span class="weather-estimate">${escapeHtml(t('historical'))}</span>` : '';
        const stale = forecast.is_stale ? `<span class="weather-estimate">${escapeHtml(t('last_saved'))}</span>` : '';
        const alertText = alerts.length
            ? `<div class="weather-alert"><strong>${escapeHtml((t('weather_alert').split('|')[alerts.length === 1 ? 0 : 1] || t('weather_alert')).replace('|', ''))}:</strong> ${alerts.map((stop) => `${escapeHtml(stop.title)} — ${escapeHtml(stop.weather.alert_reason || t('adverse'))}${indoorAlternativesMarkup(stop)}`).join('')}</div>`
            : '';

        return `<div class="weather-widget">
            <div class="weather-main"><span class="weather-icon">${weatherIcon(forecast.condition_code)}</span><strong>${escapeHtml(forecast.condition || t('weather_forecast'))}</strong><span>${number(forecast.temperature_low)}° - ${number(forecast.temperature_high)}°C</span></div>
            <div class="weather-meta"><span>${escapeHtml(t('rain'))} ${number(forecast.precipitation_probability)}%</span><span>${escapeHtml(t('humidity'))} ${number(forecast.humidity)}%</span><span>${escapeHtml(t('uv'))} ${number(forecast.uv_index)}</span>${estimate}${stale}</div>
            ${alertText}
        </div>`;
    }

    function indoorAlternativesMarkup(stop) {
        const alternatives = Array.isArray(stop.indoor_alternatives) ? stop.indoor_alternatives : [];

        if (!alternatives.length) {
            return `<span class="indoor-empty">${escapeHtml(t('indoor_generic'))}</span>`;
        }

        return `<span class="indoor-suggestions">${escapeHtml(t('indoor_alternatives'))} ${alternatives.map((alternative) => `<a href="${escapeAttribute(alternative.url)}">${escapeHtml(alternative.name)}</a>`).join(', ')}.</span>`;
    }

    /**
     * Render the resolved Malaysian place and its forecast directly beside a stop.
     */
    function weatherForItem(item) {
        const stop = weatherByStopId[String(item.item_id)];

        if (!stop) {
            return '';
        }

        if (!stop.weather.available) {
            return `<span class="item-weather-muted">${escapeHtml(t('weather_unavailable'))}</span>`;
        }

        const forecast = stop.weather;
        const alert = stop.requires_weather_alert ? `<span class="item-weather-alert">${escapeHtml(t(forecast.is_severe ? 'severe_weather' : 'rain_alert'))}</span>` : '';
        const estimate = forecast.is_historical_estimate ? `<span class="item-weather-muted">${escapeHtml(t('climate_estimate'))}</span>` : '';

        return `<span class="item-place-weather"><span class="item-weather" title="${escapeAttribute(forecast.condition || t('weather_forecast'))}">${weatherIcon(forecast.condition_code)} ${number(forecast.temperature_low)}°-${number(forecast.temperature_high)}°C · ${escapeHtml(t('rain'))} ${number(forecast.precipitation_probability)}%</span>${estimate}${alert}</span>`;
    }

    /**
     * Convert a weather code into a compact visual indicator.
     */
    function weatherIcon(code) {
        if ([61, 63, 65, 66, 67, 80, 81, 82].includes(Number(code))) return '☂';
        if ([95, 96, 99].includes(Number(code))) return 'ϟ';
        if ([71, 73, 75, 77, 85, 86].includes(Number(code))) return '❄';
        if ([1, 2, 3].includes(Number(code))) return '☁';
        return '☀';
    }

    /**
     * Apply an authoritative server snapshot and cache it locally.
     */
    function applyPayload(itinerary, message) {
        if (itinerary) {
            state = clone(itinerary);
        }

        cacheCurrentState();
        render();

        if (message) {
            showBanner(message);
        }
    }

    /**
     * Cache the latest state in browser storage for fast offline viewing.
     */
    function cacheCurrentState() {
        try {
            localStorage.setItem(cacheKey, JSON.stringify({ itinerary: state, deletedIds, offlineDirty, savedAt: new Date().toISOString() }));
        } catch (error) {
            // The live interface still works if storage is unavailable or full.
        }
    }

    /**
     * Prefer an unfinished local snapshot when offline or pending sync.
     */
    function hydrateFromCache() {
        try {
            const cached = JSON.parse(localStorage.getItem(cacheKey));

            if (cached && cached.itinerary && (!navigator.onLine || cached.offlineDirty)) {
                state = cached.itinerary;
                deletedIds = Array.isArray(cached.deletedIds) ? cached.deletedIds : [];
                offlineDirty = Boolean(cached.offlineDirty);

                if (!navigator.onLine) {
                    showBanner(t('cached_view'), true);
                }
            }
        } catch (error) {
            localStorage.removeItem(cacheKey);
        }
    }

    /**
     * Mark the current browser snapshot as pending cloud synchronization.
     */
    function markOfflineChange(message) {
        offlineDirty = true;
        cacheCurrentState();
        showBanner(message, true);
    }

    /**
     * Send a JSON request with Laravel's CSRF token and normalized errors.
     */
    async function request(url, method, body) {
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body === undefined ? undefined : JSON.stringify(body)
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessage = payload.errors ? Object.values(payload.errors).flat().join(' ') : null;
                throw { message: validationMessage || payload.message || 'The request could not be completed.', network: false };
            }

            return payload;
        } catch (error) {
            if (error.network === false) {
                throw error;
            }

            throw { message: 'Connection unavailable.', network: true };
        }
    }

    /**
     * Render an error message without losing the current local itinerary state.
     */
    function handleRequestFailure(error) {
        showBanner(error.message || t('request_failed'), Boolean(error.network));
    }

    /**
     * Show a temporary status message above the itinerary workspace.
     */
    function showBanner(message, warning) {
        if (!syncBanner) {
            return;
        }

        syncBanner.textContent = message;
        syncBanner.hidden = false;
        syncBanner.classList.toggle('is-warning', Boolean(warning));

        if (!warning) {
            window.clearTimeout(showBanner.timeout);
            showBanner.timeout = window.setTimeout(() => { syncBanner.hidden = true; }, 5000);
        }
    }

    /**
     * Build the owner/shared item mutation URL for a single item.
     */
    function itemUrl(itemId) {
        return config.itemUrlTemplate.replace('__ITEM__', encodeURIComponent(itemId));
    }

    /**
     * Build the owner/shared item deletion URL for a single item.
     */
    function deleteUrl(itemId) {
        return config.itemDeleteUrlTemplate.replace('__ITEM__', encodeURIComponent(itemId));
    }

    /**
     * Build the owner/shared eco-alternative URL for a single item.
     */
    function ecoUrl(itemId) {
        return config.ecoUrlTemplate.replace('__ITEM__', encodeURIComponent(itemId));
    }

    /**
     * Find an item by its current client or server identifier.
     */
    function findItem(itemId) {
        return state.items.find((item) => String(item.item_id) === String(itemId));
    }

    /**
     * Sort items exactly as the server displays them.
     */
    function sortedItems() {
        return [...state.items].sort((a, b) => {
            const aDate = a.scheduled_date || '9999-12-31';
            const bDate = b.scheduled_date || '9999-12-31';
            const aTime = a.start_time || '23:59';
            const bTime = b.start_time || '23:59';

            return aDate.localeCompare(bDate) || aTime.localeCompare(bTime) || Number(a.sort_order || 0) - Number(b.sort_order || 0);
        });
    }

    /**
     * Group items by their planned date, retaining an unscheduled fallback group.
     */
    function groupItemsByDate(items) {
        return items.reduce((groups, item) => {
            const date = item.scheduled_date || 'unscheduled';
            groups[date] = groups[date] || [];
            groups[date].push(item);
            return groups;
        }, {});
    }

    /**
     * Format a date for the visual day rail.
     */
    function dateLabel(date) {
        if (date === 'unscheduled') {
            return { weekday: 'Flexible', month: 'Date', day: '—', year: '' };
        }

        const value = new Date(`${date}T12:00:00`);
        return {
            weekday: value.toLocaleDateString(undefined, { weekday: 'long' }),
            month: value.toLocaleDateString(undefined, { month: 'short' }),
            day: String(value.getDate()),
            year: String(value.getFullYear())
        };
    }

    /**
     * Create a small summary for the selected layout control.
     */
    function summaryText() {
        const count = state.items.length;

        if (currentView === 'map') {
            const mappedCount = state.items.filter(hasValidCoordinates).length;
            return t(mappedCount === 1 ? 'mapped_stop' : 'mapped_stops', { count: mappedCount });
        }

        return t(count === 1 ? 'planned_item' : 'planned_items', { count });
    }

    /**
     * Decide if drag reordering can be synchronized by the active session.
     */
    function canReorder() {
        return config.canEdit && !config.isShared && Boolean(config.reorderUrl);
    }

    /**
     * Check whether an item has a server-generated numeric identifier.
     */
    function isServerItem(item) {
        return /^\d+$/.test(String(item.item_id));
    }

    /**
     * Approximate the server-side transport emissions calculation for offline edits.
     */
    function estimateCarbon(mode, distance) {
        const factors = { flight: 0.255, private_car: 0.192, taxi: 0.192, ferry: 0.115, bus: 0.105, train: 0.041, electric_train: 0.035, walking: 0, cycling: 0 };
        return Math.round(Math.max(0, distance) * (factors[mode] || 0) * 100) / 100;
    }

    /**
     * Mirror the server-side high-emission suggestions for offline planning.
     */
    function ecoSuggestion(mode, distance) {
        const alternatives = {
            flight: ['electric_train', 'Electric train', 'Rail is a lower-carbon option for many domestic routes.'],
            private_car: ['train', 'Train', 'Public rail reduces emissions per traveller.'],
            taxi: ['bus', 'Bus', 'A shared bus trip has a smaller footprint per traveller.']
        };
        const alternative = alternatives[mode];

        if (!alternative) {
            return null;
        }

        const current = estimateCarbon(mode, distance);
        const replacement = estimateCarbon(alternative[0], distance);

        return { mode: alternative[0], label: alternative[1], reason: alternative[2], current_carbon_kg: current, alternative_carbon_kg: replacement, saving_kg: Math.max(0, current - replacement) };
    }

    /**
     * Format a transport mode for the item card.
     */
    function transportLabel(mode) {
        const transportLabels = { flight: 'Flight', private_car: 'Private car', taxi: 'Taxi', ferry: 'Ferry', bus: 'Bus', train: 'Train', electric_train: 'Electric train', walking: 'Walking', cycling: 'Cycling' };
        return transportLabels[mode] || 'Not specified';
    }

    /**
     * Format a 24-hour time value for concise card display.
     */
    function formatTime(value) {
        const [hour, minute] = value.split(':').map(Number);
        const suffix = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${displayHour}:${String(minute).padStart(2, '0')} ${suffix}`;
    }

    /**
     * Format a footprint figure without unnecessary trailing decimals.
     */
    function number(value) {
        return new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(Number(value || 0));
    }

    /**
     * Escape arbitrary user content before inserting it into markup.
     */
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
    }

    /**
     * Escape a value placed inside an HTML attribute.
     */
    function escapeAttribute(value) {
        return escapeHtml(value);
    }

    /**
     * Create a plain mutable copy of server-provided state.
     */
    function clone(value) {
        return JSON.parse(JSON.stringify(value));
    }
}());
