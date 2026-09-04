<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $trip->title }} - ExploreMY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/itinerary.css') }}?v={{ filemtime(public_path('css/itinerary.css')) }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body class="itinerary-body">

@include('components.navbar')
<x-page-back :href="$shareToken ? route('explore') : route('itineraries.index')" :label="__('itinerary.back')" />

<main class="itinerary-page itinerary-detail-page">
    <div class="container">
        @if(session('status'))
            <div class="notice notice-success" role="status">{{ session('status') }}</div>
        @endif

        <section class="itinerary-titlebar">
            <div>
                <div class="title-line">
                    <h1>{{ $trip->title }}</h1>
                    @if($sharedPermission)
                        <span class="permission-badge">{{ $sharedPermission === 'edit' ? __('trip.collab') : __('trip.view_only') }}</span>
                    @endif
                </div>
                <p>{{ $trip->destination ?: 'Malaysia' }} · {{ $trip->start_date?->translatedFormat('d M Y') ?? __('trip.dates_tbc') }}{{ $trip->end_date ? ' - '.$trip->end_date->translatedFormat('d M Y') : '' }}</p>
            </div>
            <div class="title-actions">
                @if(!$shareToken)
                    <button type="button" class="button button-outline" data-action="refresh-weather">{{ __('itinerary.refresh_weather') }}</button>
                    <div class="export-menu">
                        <button type="button" class="button button-outline" data-action="toggle-export">{{ __('itinerary.export') }} ▾</button>
                        <div class="export-popover" id="export-popover" hidden>
                            <a href="{{ route('itineraries.export.pdf', $trip) }}">{{ __('itinerary.download_pdf') }}</a>
                            <a href="{{ route('itineraries.export.calendar', $trip) }}">{{ __('itinerary.download_calendar') }}</a>
                        </div>
                    </div>
                    <button type="button" class="button button-outline" data-action="open-share-dialog">{{ __('itinerary.share') }}</button>
                @endif
            </div>
        </section>

        <div class="sync-banner" id="sync-banner" role="status" hidden></div>

        <section class="view-switcher card-surface" aria-label="{{ __('itinerary.layout') }}">
            <div class="segmented-control" role="tablist" aria-label="{{ __('itinerary.layout') }}">
                <button class="segment is-active" type="button" data-view="timeline" role="tab" aria-selected="true">{{ __('itinerary.timeline') }}</button>
                <button class="segment" type="button" data-view="agenda" role="tab" aria-selected="false">{{ __('itinerary.agenda') }}</button>
                <button class="segment" type="button" data-view="map" role="tab" aria-selected="false">{{ __('itinerary.map') }}</button>
            </div>
            <div class="view-summary" id="view-summary"></div>
        </section>

        <section class="itinerary-workspace">
            <div class="itinerary-primary">
                <section id="items-panel" class="items-panel" aria-live="polite">
                    <div id="itinerary-items">
                        @if($trip->items->isEmpty())
                            <div class="empty-itinerary card-surface">
                                <div class="empty-illustration" aria-hidden="true">✦</div>
                                <h2>{{ __('trip.empty_title') }}</h2>
                                <p>{{ __('trip.empty_text') }}</p>
                                @if($canEdit)
                                    <button class="button button-primary" type="button" data-action="open-item-dialog">{{ __('trip.add') }}</button>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div id="map-view" class="map-view card-surface" hidden>
                        <div id="itinerary-map" aria-label="{{ __('itinerary.map_label') }}"></div>
                        <p class="map-help">{{ __('itinerary.map_help') }}</p>
                    </div>
                </section>

                <section class="reorder-hint" id="reorder-hint" @if(! $canEdit) hidden @endif>
                    <span aria-hidden="true">↕</span> {{ __('trip.reorder') }}
                </section>
            </div>

            <aside class="itinerary-sidebar">
                <section class="carbon-card card-surface">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">{{ __('trip.eco') }}</span>
                            <h2>{{ __('trip.carbon') }}</h2>
                        </div>
                        <span class="leaf-mark" aria-hidden="true">♧</span>
                    </div>
                    <div class="carbon-total">
                        <span id="carbon-total">0</span>
                        <small>{{ __('trip.estimated') }}</small>
                    </div>
                    <div class="carbon-bar"><span id="carbon-bar"></span></div>
                    <div class="carbon-breakdown" id="carbon-breakdown"></div>
                </section>

                <section class="quick-actions card-surface">
                    <span class="eyebrow">{{ __('trip.quick') }}</span>
                    <h2>{{ __('trip.keep') }}</h2>
                    <ul>
                        <li><span aria-hidden="true">✓</span> {{ __('trip.cloud') }}</li>
                        <li><span aria-hidden="true">✓</span> {{ __('trip.offline') }}</li>
                        <li><span aria-hidden="true">✓</span> {{ __('trip.alternative') }}</li>
                    </ul>
                </section>
            </aside>
        </section>
    </div>
</main>

@if($canEdit)
<dialog class="app-dialog item-dialog" id="item-dialog" aria-labelledby="item-dialog-title">
    <form id="item-form" class="dialog-form">
        <input type="hidden" name="item_id" id="item-id">
        <div class="dialog-heading">
            <div>
                <span class="eyebrow">{{ __('trip.item') }}</span>
                <h2 id="item-dialog-title">{{ __('itinerary.client.add_item') }}</h2>
            </div>
            <button type="button" class="icon-button" aria-label="Close" data-action="close-item-dialog">×</button>
        </div>
        <div class="form-grid">
            <label>
                {{ __('trip.type') }}
                <select name="category" id="item-category" required>
                    <option value="transport">{{ __('itinerary.client.transport') }}</option>
                    <option value="lodging">{{ __('itinerary.client.lodging') }}</option>
                    <option value="activity" selected>{{ __('itinerary.client.activity') }}</option>
                    <option value="food">{{ __('itinerary.client.food') }}</option>
                    <option value="sightseeing">{{ __('itinerary.client.sightseeing') }}</option>
                </select>
            </label>
            <label>
                {{ __('trip.title') }}
                <input name="title" id="item-title" required maxlength="160" placeholder="e.g. KL Sentral to Penang">
            </label>
        </div>
        <div class="form-grid form-grid-three">
            <label>
                {{ __('trip.date') }}
                <input type="date" name="scheduled_date" id="item-date" value="{{ $trip->start_date?->toDateString() }}">
            </label>
            <label>
                {{ __('trip.start') }}
                <input type="time" name="start_time" id="item-start-time">
            </label>
            <label>
                {{ __('trip.end') }}
                <input type="time" name="end_time" id="item-end-time">
            </label>
        </div>
        <label>
            {{ __('trip.location') }}
            <input name="location" id="item-location" maxlength="180" placeholder="e.g. George Town, Penang">
        </label>
        <div class="transport-fields" id="transport-fields">
            <div class="form-grid">
                <label>
                    {{ __('trip.mode') }}
                    <select name="transport_mode" id="item-transport-mode">
                        <option value="">{{ __('trip.choose') }}</option>
                        <option value="flight">Flight</option>
                        <option value="private_car">Private car</option>
                        <option value="taxi">Taxi</option>
                        <option value="ferry">Ferry</option>
                        <option value="bus">Bus</option>
                        <option value="train">Train</option>
                        <option value="electric_train">Electric train</option>
                        <option value="walking">Walking</option>
                        <option value="cycling">Cycling</option>
                    </select>
                </label>
                <label>
                    {{ __('trip.distance') }}
                    <input type="number" name="distance_km" id="item-distance" min="0" max="50000" step="0.1" placeholder="e.g. 355">
                </label>
            </div>
            <p class="field-help">{{ __('trip.transport_help') }}</p>
        </div>
        <label>
            {{ __('trip.notes') }} <span class="muted">({{ __('trip.optional') }})</span>
            <textarea name="notes" id="item-notes" rows="3" maxlength="3000" placeholder="Booking details, accessibility notes, or a reminder."></textarea>
        </label>
        <details class="coordinates-details">
            <summary>{{ __('trip.coordinates') }} <span class="muted">({{ __('trip.optional') }})</span></summary>
            <div class="form-grid">
                <label>{{ __('trip.latitude') }} <input type="number" name="latitude" id="item-latitude" min="-90" max="90" step="0.0000001" placeholder="5.4141"></label>
                <label>{{ __('trip.longitude') }} <input type="number" name="longitude" id="item-longitude" min="-180" max="180" step="0.0000001" placeholder="100.3288"></label>
            </div>
        </details>
        <div class="dialog-actions">
            <button type="button" class="button button-quiet" data-action="close-item-dialog">{{ __('trip.cancel') }}</button>
            <button class="button button-primary" type="submit" id="item-submit">{{ __('trip.save') }}</button>
        </div>
    </form>
</dialog>
@endif

@if(!$shareToken)
<dialog class="app-dialog" id="share-dialog" aria-labelledby="share-dialog-title">
    <form id="share-form" class="dialog-form">
        <div class="dialog-heading">
            <div>
                <span class="eyebrow">{{ __('trip.share') }}</span>
                <h2 id="share-dialog-title">{{ __('trip.invite') }}</h2>
            </div>
            <button type="button" class="icon-button" aria-label="Close" data-action="close-share-dialog">×</button>
        </div>
        <label>
            {{ __('trip.permission') }}
            <select name="permission" id="share-permission">
                <option value="view">{{ __('trip.view_recommended') }}</option>
                <option value="edit">{{ __('trip.edit') }}</option>
            </select>
        </label>
        <label>
            {{ __('trip.expiry') }} <span class="muted">({{ __('trip.optional') }})</span>
            <input type="datetime-local" name="expires_at" id="share-expiry">
        </label>
        <p class="field-help">{{ __('trip.share_help') }}</p>
        <div id="share-result" class="share-result" hidden>
            <label>{{ __('trip.secure') }} <input id="share-url" readonly></label>
            <button type="button" class="button button-outline" data-action="copy-share">{{ __('trip.copy') }}</button>
        </div>
        <div class="dialog-actions">
            <button type="button" class="button button-quiet" data-action="close-share-dialog">{{ __('trip.close') }}</button>
            <button class="button button-primary" type="submit">{{ __('trip.generate') }}</button>
        </div>
    </form>
</dialog>
@endif

<script>
window.itineraryConfig = @json($clientConfig);
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/itinerary.js') }}?v={{ filemtime(public_path('js/itinerary.js')) }}"></script>

@include('components.footer')

</body>
</html>
