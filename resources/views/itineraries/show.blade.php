<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $trip->title }} - ExploreMY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/itinerary.css') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body class="itinerary-body">

@include('components.navbar')

<main class="itinerary-page itinerary-detail-page">
    <div class="container">
        @if(session('status'))
            <div class="notice notice-success" role="status">{{ session('status') }}</div>
        @endif

        <section class="itinerary-titlebar">
            <div>
                <a class="back-link" href="{{ auth()->check() ? route('itineraries.index') : '#' }}">← My trips</a>
                <div class="title-line">
                    <h1>{{ $trip->title }}</h1>
                @if($sharedPermission)
                        <span class="permission-badge">View-only link</span>
                    @endif
                </div>
                <p>{{ $trip->destination ?: 'Malaysia' }} · {{ $trip->start_date?->format('d M Y') ?? 'Dates to be confirmed' }}{{ $trip->end_date ? ' - '.$trip->end_date->format('d M Y') : '' }}</p>
            </div>
            <div class="title-actions">
                @if(!$shareToken)
                    <button type="button" class="button button-outline" data-action="refresh-weather">Refresh weather</button>
                    <button type="button" class="button button-primary" data-action="save">Save changes</button>
                    <a class="button button-outline" href="{{ route('itineraries.export.pdf', $trip) }}">Export PDF</a>
                    <button type="button" class="button button-outline" data-action="open-share-dialog">Share</button>
                @endif
            </div>
        </section>

        <div class="sync-banner" id="sync-banner" role="status" hidden></div>

        <section class="view-switcher card-surface" aria-label="Itinerary layout">
            <div class="segmented-control" role="tablist" aria-label="Choose a layout">
                <button class="segment is-active" type="button" data-view="timeline" role="tab" aria-selected="true">Timeline</button>
                <button class="segment" type="button" data-view="agenda" role="tab" aria-selected="false">Daily agenda</button>
                <button class="segment" type="button" data-view="map" role="tab" aria-selected="false">Map</button>
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
                                <h2>Add your first activity</h2>
                                <p>Start with transport, a stay, food, or an activity. We will calculate its estimated footprint as you plan.</p>
                                @if($canEdit)
                                    <button class="button button-primary" type="button" data-action="open-item-dialog">Add activity</button>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div id="map-view" class="map-view card-surface" hidden>
                        <div id="itinerary-map" aria-label="Itinerary location map"></div>
                        <p class="map-help">Add optional latitude and longitude to an itinerary item to place it precisely on the map.</p>
                    </div>
                </section>

                <section class="reorder-hint" id="reorder-hint" @if(! $canEdit) hidden @endif>
                    <span aria-hidden="true">↕</span> Drag cards within a day to reorder them. Changes save automatically when you are online.
                </section>
            </div>

            <aside class="itinerary-sidebar">
                <section class="carbon-card card-surface">
                    <div class="section-heading">
                        <div>
                            <span class="eyebrow">Itinerary eco-dashboard</span>
                            <h2>Carbon footprint</h2>
                        </div>
                        <span class="leaf-mark" aria-hidden="true">♧</span>
                    </div>
                    <div class="carbon-total">
                        <span id="carbon-total">0</span>
                        <small>kg CO<sub>2</sub>e estimated</small>
                    </div>
                    <div class="carbon-bar"><span id="carbon-bar"></span></div>
                    <div class="carbon-breakdown" id="carbon-breakdown"></div>
                </section>

                <section class="quick-actions card-surface">
                    <span class="eyebrow">Quick actions</span>
                    <h2>Keep planning</h2>
                    <ul>
                        <li><span aria-hidden="true">✓</span> Every online edit is saved to your cloud itinerary.</li>
                        <li><span aria-hidden="true">✓</span> Offline edits are kept on this device and synced when you reconnect.</li>
                        <li><span aria-hidden="true">✓</span> High-emission transport gets a lower-carbon alternative.</li>
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
                <span class="eyebrow">Itinerary item</span>
                <h2 id="item-dialog-title">Add an item</h2>
            </div>
            <button type="button" class="icon-button" aria-label="Close" data-action="close-item-dialog">×</button>
        </div>
        <div class="form-grid">
            <label>
                Type
                <select name="category" id="item-category" required>
                    <option value="transport">Transport</option>
                    <option value="lodging">Lodging</option>
                    <option value="activity" selected>Activity</option>
                    <option value="food">Food</option>
                    <option value="sightseeing">Sightseeing</option>
                </select>
            </label>
            <label>
                Title
                <input name="title" id="item-title" required maxlength="160" placeholder="e.g. KL Sentral to Penang">
            </label>
        </div>
        <div class="form-grid form-grid-three">
            <label>
                Date
                <input type="date" name="scheduled_date" id="item-date" value="{{ $trip->start_date?->toDateString() }}">
            </label>
            <label>
                Start time
                <input type="time" name="start_time" id="item-start-time">
            </label>
            <label>
                End time
                <input type="time" name="end_time" id="item-end-time">
            </label>
        </div>
        <label>
            Location
            <input name="location" id="item-location" maxlength="180" placeholder="e.g. George Town, Penang">
        </label>
        <div class="transport-fields" id="transport-fields">
            <div class="form-grid">
                <label>
                    Transport mode
                    <select name="transport_mode" id="item-transport-mode">
                        <option value="">Choose a mode</option>
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
                    Distance (km)
                    <input type="number" name="distance_km" id="item-distance" min="0" max="50000" step="0.1" placeholder="e.g. 355">
                </label>
            </div>
            <p class="field-help">Flights, private cars, and taxis will show a lower-carbon suggestion after saving.</p>
        </div>
        <label>
            Notes <span class="muted">(optional)</span>
            <textarea name="notes" id="item-notes" rows="3" maxlength="3000" placeholder="Booking details, accessibility notes, or a reminder."></textarea>
        </label>
        <details class="coordinates-details">
            <summary>Map coordinates <span class="muted">(optional)</span></summary>
            <div class="form-grid">
                <label>Latitude <input type="number" name="latitude" id="item-latitude" min="-90" max="90" step="0.0000001" placeholder="5.4141"></label>
                <label>Longitude <input type="number" name="longitude" id="item-longitude" min="-180" max="180" step="0.0000001" placeholder="100.3288"></label>
            </div>
        </details>
        <div class="dialog-actions">
            <button type="button" class="button button-quiet" data-action="close-item-dialog">Cancel</button>
            <button class="button button-primary" type="submit" id="item-submit">Save item</button>
        </div>
    </form>
</dialog>
@endif

@if(!$shareToken)
<dialog class="app-dialog" id="share-dialog" aria-labelledby="share-dialog-title">
    <form id="share-form" class="dialog-form">
        <div class="dialog-heading">
            <div>
                <span class="eyebrow">Share itinerary</span>
                <h2 id="share-dialog-title">Invite a collaborator</h2>
            </div>
            <button type="button" class="icon-button" aria-label="Close" data-action="close-share-dialog">×</button>
        </div>
        <label>
            Share permission
            <input type="text" value="View only" readonly>
            <input type="hidden" name="permission" value="view">
        </label>
        <label>
            Link expiry <span class="muted">(optional)</span>
            <input type="datetime-local" name="expires_at" id="share-expiry">
        </label>
        <p class="field-help">Each link uses a unique secret token. You can create a separate link whenever access needs to change.</p>
        <div id="share-result" class="share-result" hidden>
            <label>Secure link <input id="share-url" readonly></label>
            <button type="button" class="button button-outline" data-action="copy-share">Copy link</button>
        </div>
        <div class="dialog-actions">
            <button type="button" class="button button-quiet" data-action="close-share-dialog">Close</button>
            <button class="button button-primary" type="submit">Generate link</button>
        </div>
    </form>
</dialog>
@endif

<script>
window.itineraryConfig = @json($clientConfig);
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/itinerary.js') }}"></script>

@include('components.footer')

</body>
</html>
