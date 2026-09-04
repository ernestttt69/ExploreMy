<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('transport.title') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/transport.css') }}">
</head>
<body class="transport-page">

@include('components.navbar')
<x-page-back :href="route('dashboard')" :label="__('ui.profile.back')" />

<div class="transport-container">

    <!-- System Alerts -->
    @if(session('error') || isset($error))
        <div class="alert alert-danger">
            ⚠️ {{ session('error') ?? $error }}
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning">
            ⚠️ {{ session('warning') }}
        </div>
    @endif

    @if(session('info') || isset($info))
        <div class="alert alert-info">
            ℹ️ {{ session('info') ?? $info }}
        </div>
    @endif

    @if(session('selected_info'))
        <div class="alert alert-info">
            ℹ️ <strong>{{ session('selected_info')['name'] }}</strong><br>
            {{ __('transport.hours') }} {{ session('selected_info')['hours'] }}<br>
            {{ __('transport.status') }} {{ session('selected_info')['status'] }}
        </div>
    @endif

    <!-- Section 1: Route Search Form & Nearby Search -->
    <div class="card-panel">
        <h2 class="card-title">{{ __('transport.heading') }}</h2>
        
        <div class="gmaps-card">
            <form action="{{ route('transport.search') }}" method="GET" id="search-form">
                
                <!-- Travel Mode Selector Toggle -->
                <div class="mode-toggle-group">
                    <label class="mode-btn-option {{ ($mode ?? 'transit') === 'transit' ? 'active' : '' }}" onclick="selectMode(this)">
                        <input type="radio" name="mode" value="transit" {{ ($mode ?? 'transit') === 'transit' ? 'checked' : '' }}>
                        <span class="mode-icon" aria-hidden="true">🚉</span>
                        {{ __('transport.public_transport') }}
                    </label>
                    <label class="mode-btn-option {{ ($mode ?? '') === 'walking' ? 'active' : '' }}" onclick="selectMode(this)">
                        <input type="radio" name="mode" value="walking" {{ ($mode ?? '') === 'walking' ? 'checked' : '' }}>
                        <span class="mode-icon" aria-hidden="true">🚶‍♀️</span>
                        {{ __('transport.walking') }}
                    </label>
                </div>
<div class="gmaps-input-group">
            <div class='gmaps-fields-wrapper'>
                <!-- Starting Location -->
                <div class="gmaps-field">
                    <span class="gmaps-dot start-dot"></span>
                    <input 
                        type="text" 
                        id="origin-input" 
                        name="origin" 
                        class="gmaps-input" 
                placeholder="{{ __('transport.origin_placeholder') }}"
                        value="{{ old('origin', $origin ?? '') }}" 
                        required 
                        autocomplete="off">
                </div>


                <!-- Destination -->
                <div class="gmaps-field">
                    <span class="gmaps-dot end-dot"></span>
                    <input 
                        type="text" 
                        id="destination-input" 
                        name="destination" 
                        class="gmaps-input" 
                placeholder="{{ __('transport.destination_placeholder') }}"
                        value="{{ old('destination', $destination ?? '') }}" 
                        required 
                        autocomplete="off">
                </div>
            </div>
                <!-- Connecting Line -->
                <div class="gmaps-connector">
                    <button type="button" class="btn-swap" onclick="swapLocations()" title="{{ __('misc.transport.swap') }}">
                        ⇅
                    </button>
                </div>
        </div>
            <button type="submit" class="gmaps-btn">{{ __('transport.search') }}</button>
            </form>

            <!-- Trigger for View Nearby Stations -->
            <button type="button" class="btn-nearby" onclick="fetchNearbyStations()">
                📍 {{ __('transport.nearby') }}
            </button>

            <!-- Hidden form for GPS coordinates -->
            <form id="nearbyForm" action="{{ route('transport.nearby') }}" method="GET" style="display:none;">
                <input type="hidden" name="lat" id="latInput">
                <input type="hidden" name="lng" id="lngInput">
                <input type="hidden" name="manual_location" id="manualLocationInput">
            </form>
        </div>
    </div>

<!-- Section 2: Transport Line Info & Static Route Map -->
    <div class="card-panel">
        <div class="service-header" onclick="toggleServiceInfo()">
            <h2 class="card-title">ℹ️ {{ __('transport.line_heading') }}</h2>
            <span id="toggle-icon">+</span>
        </div>

        <div id="service-info-panel" class="service-panel-hidden">
            <p class="service-panel-desc">{{ __('transport.line_intro') }}</p>
            <form action="{{ route('transport.line-info') }}" method="GET" class="service-form">
                <select name="line_code" class="form-control" style="max-width: 320px;">
                    <option value="">{{ __('transport.select_line') }}</option>
                    <option value="KJ">LRT Kelana Jaya Line (KJ)</option>
                    <option value="AG">LRT Ampang Line (AG)</option>
                    <option value="KG">MRT Kajang Line (KG)</option>
                    <option value="PY">MRT Putrajaya Line (PY)</option>
                    <option value="MR">KL Monorail Line (MR)</option>
                    <option value="SA">LRT Shah Alam Line (SA)</option>
                    <option value="KTM">KTM Komuter Line</option>
                </select>
                <button type="submit" class="btn-service">{{ __('transport.check_line') }}</button>
            </form>

            <!-- Display Line Info Result -->
            @if(session('selected_info'))
                @php $info = session('selected_info'); @endphp
                <div class="line-status-card" style="border-left: 5px solid {{ $info['color'] }}; background: #f8fafc; padding: 16px; border-radius: 12px; margin-top: 16px;">
                    <h3 style="color: #1b4332; margin-bottom: 8px;">{{ $info['name'] }}</h3>
                    <div style="font-size: 13px; display: grid; gap: 6px;">
                        <div>🏢 <strong>{{ __('transport.operator') }}</strong> {{ $info['operator'] }}</div>
                        <div>🕒 <strong>{{ __('transport.hours') }}</strong> {{ $info['hours'] }}</div>
                        <div>⚡ <strong>{{ __('transport.frequency') }}</strong> {{ $info['frequency'] }}</div>
                        <div>
                            🟢 <strong>{{ __('transport.status') }}</strong>
                            <span class="fare-tag" style="background: {{ $info['status'] === __('messages.transport_status_normal') ? '#dcfce7' : '#fef3c7' }}; color: {{ $info['status'] === __('messages.transport_status_normal') ? '#166534' : '#92400e' }};">
                                {{ $info['status'] }}
                            </span>
                        </div>
                        <div>⚠️ <strong>{{ __('transport.disruptions') }}</strong> {{ $info['disruptions'] }}</div>
                    </div>
                </div>
            @endif

            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0;">
            
            <!-- Static Image Map inside the panel -->
            <h3 style="color: #1b4332; margin-bottom: 12px; font-size: 18px;">🗺️ {{ __('transport.map') }}</h3>
            <div style="border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; background: #ffffff; text-align: center; padding: 10px;">
                <a href="{{ asset('images/route_map.png') }}" target="_blank" title="{{ __('transport.map_hint') }}">
                    <img 
                        src="{{ asset('images/route_map.png') }}" 
                        alt="Klang Valley Integrated Transit Map" 
                        style="width: 100%; height: auto; max-height: 700px; object-fit: contain; cursor: zoom-in;"
                    >
                </a>
                <p style="font-size: 12px; color: #718096; margin-top: 8px;">{{ __('transport.map_hint') }}</p>
            </div>
        </div>
    </div>

<!-- Section 3: Nearby Stations Results -->
    @if(isset($nearbyStations) && count($nearbyStations) > 0)
        <div class="card-panel">
            <h2 class="card-title">{{ __('transport.nearby') }}</h2>
            <div class="stations-grid">
                @foreach($nearbyStations as $station)
                    <div class="station-item">
                        <div style="margin-bottom: 6px;">
                            <span class="mode-btn active" style="font-size: 11px; padding: 2px 8px;">
                                {{ $station['category_badge'] }}
                            </span>
                        </div>
                        <div class="station-name">{{ $station['name'] }}</div>
                        <div class="step-desc">📍 {{ $station['address'] }}</div>
                        <div class="station-distance">📏 {{ __('transport.distance') }} <strong>{{ $station['distance'] }}</strong></div>
                        <button onclick="viewStationDetails('{{ $station['place_id'] }}')" class="btn-toggle-route" style="margin-top:8px;">
                            {{ __('transport.details') }}
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Station Details Modal / Card -->
<div id="station-details-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <button type="button" class="close-btn" onclick="closeStationDetails()">&times;</button>
        
        <h3 id="detail-station-name">{{ __('transport.station_name') }}</h3>
        <p id="detail-station-address" class="station-address">{{ __('transport.station_address') }}</p>

        <div class="details-grid">
            <div class="detail-item">
                <span class="detail-label">♿ {{ __('transport.wheelchair') }}</span>
                <span id="detail-wheelchair" class="detail-value">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">🕒 {{ __('transport.current_status') }}</span>
                <span id="detail-status" class="detail-value">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">⭐ {{ __('transport.rating') }}</span>
                <span id="detail-rating" class="detail-value">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">📞 {{ __('transport.phone') }}</span>
                <span id="detail-phone" class="detail-value">-</span>
            </div>
        </div>

        <div class="hours-section">
            <h4>{{ __('transport.operating_hours') }}</h4>
            <ul id="detail-opening-hours"></ul>
        </div>

        <div id="reviews-section" class="reviews-section">
            <h4>{{ __('transport.reviews') }}</h4>
            <div id="detail-reviews-list"></div>
        </div>

        <div class="modal-actions">
            <a id="detail-maps-link" href="#" target="_blank" class="btn-primary-modal">{{ __('transport.view_maps') }} 🗺️</a>
            <button type="button" class="btn-secondary-modal" onclick="setAsOriginFromModal()">{{ __('transport.set_origin') }} 📍</button>
        </div>
    </div>
</div>

<!-- Section 4: Route Results -->
@if(isset($routes) && count($routes) > 0)
    <div class="card-panel">
        
        <!-- Header & Instant Sorting Controls Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0;">
            <h2 class="card-title" style="margin-bottom: 0;">
                {{ ($mode ?? 'transit') === 'walking' ? '🚶 ' . __('transport.walking_routes') : '🚌 ' . __('transport.transit_routes') }}
                ({{ __('transport.options_found', ['count' => count($routes)]) }})
            </h2>

            <!-- Sorting Dropdown Bar -->
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span style="font-size: 13px; font-weight: 600; color: #475569;">⚡ {{ __('transport.sort') }}</span>
                
                <select id="routeSortSelect" onchange="sortRoutes(this.value)" style="padding: 6px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; font-size: 13px; font-weight: 500; cursor: pointer; color: #1e293b;">
                    <option value="duration">⏱️ {{ __('transport.fastest') }}</option>
                    <option value="distance">📏 {{ __('transport.shortest') }}</option>
                    <option value="fare">💰 {{ __('transport.lowest_fare') }}</option>
                </select>
            </div>
        </div>

        <!-- Container holding the route option cards -->
        <div id="routes-container">
            @foreach($routes as $index => $route)
                <div class="station-item route-card" 
                     style="margin-bottom: 16px;" 
                     data-duration="{{ $route['duration_val'] }}" 
                     data-distance="{{ $route['distance_val'] }}" 
                     data-fare="{{ $route['fare_val'] }}">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong class="option-title" style="color:#1b4332; font-size:16px;">{{ __('transport.option', ['number' => $index + 1]) }} {{ $route['duration'] }}</strong>
                            <span class="step-desc">({{ $route['distance'] }})</span>
                        </div>
                        <span class="fare-tag">
                            {{ $route['mode'] === 'walking' ? __('transport.free') . ' 🚶' : __('transport.total_fare', ['fare' => $route['total_fare']]) }}
                        </span>
                    </div>

                    @if(!empty($route['legs_summary']))
                        <div class="transport-modes" style="margin-top: 8px;">
                            @foreach($route['legs_summary'] as $badge)
                                <span class="mode-btn active">{{ $badge }}</span>
                            @endforeach
                        </div>
                    @endif

<div style="display: flex; gap: 8px; margin-top: 10px;">
    <!-- Toggle Step-by-Step Directions -->
    <button class="btn-toggle-route" onclick="showRouteDetails({{ $index }})" style="flex: 1;">
        {{ __('transport.select_route') }} 👇
    </button>

    <!-- Export / Print PDF Button -->
    <button type="button" class="btn-toggle-route" onclick="exportRoute({{ $index }}, this)" data-reward-url="{{ route('rewards.activity') }}" style="background-color: #2d6a4f; color: white; width: auto; padding: 0 16px;">
        📄 {{ __('transport.export') }}
    </button>
</div>

                    <div id="route-details-{{ $index }}" class="timeline timeline-hidden" style="margin-top: 12px;">
                        @foreach($route['steps'] as $step)
                            <div class="timeline-step {{ !empty($step['is_transfer']) ? 'transfer-step' : '' }}">
                                <div class="step-title">
                                    {{ $step['icon'] }} {{ $step['title'] }}
                                    
                                    @if(!empty($step['is_transfer']))
                                        <span class="transfer-badge">🔄 {{ __('transport.transfer') }}</span>
                                    @endif

                                    <span class="step-fare-tag">
                                        {{ $step['type'] === 'walking' ? 'Free' : 'Fare: RM ' . $step['fare'] }}
                                    </span>
                                </div>

                                <div class="step-desc" style="margin-top: 4px;">
                                    {{ $step['instructions'] }}
                                </div>

                                @if($step['type'] === 'transit')
                                    <div style="font-size: 12px; color: #555; margin-top: 4px;">
                                        📍 <strong>{{ __('transport.board') }}</strong> {{ $step['dep_station'] }} <br>
                                        🏁 <strong>{{ __('transport.alight') }}</strong> {{ $step['arr_station'] }} ({{ __('transport.stops', ['count' => $step['num_stops']]) }}, {{ $step['distance'] }})
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

    </div>
@endif

</div>

<script>
@php($transportTranslations = [
    'gpsFailed' => __('misc.transport.gps_failed'),
    'gpsUnsupported' => __('misc.transport.gps_unsupported'),
    'option' => __('misc.transport.option', ['number' => ':number']),
])
const transportTranslations = {{ Illuminate\Support\Js::from($transportTranslations) }};
function selectMode(element) {
    document.querySelectorAll('.mode-btn-option').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
    const radio = element.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
}

function toggleServiceInfo() {
    const panel = document.getElementById('service-info-panel');
    const icon = document.getElementById('toggle-icon');
    if (panel.classList.contains('service-panel-hidden')) {
        panel.classList.remove('service-panel-hidden');
        icon.innerText = '-';
    } else {
        panel.classList.add('service-panel-hidden');
        icon.innerText = '+';
    }
}

function showRouteDetails(index) {
    const detailPanel = document.getElementById('route-details-' + index);
    detailPanel.classList.toggle('timeline-hidden');
}

function findNearbyStations() {
    const originInput = document.getElementById('origin-input');
    
    if (!originInput || originInput.value.trim() === '') {
        alert({{ Illuminate\Support\Js::from(__('transport.enter_origin')) }});
        setTimeout(() => {
            originInput.focus();
            originInput.style.border = "2px solid #e63946";
        }, 100);
        return false;
    }

    originInput.style.border = "";

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                document.getElementById('latInput').value = position.coords.latitude;
                document.getElementById('lngInput').value = position.coords.longitude;
                document.getElementById('manualLocationInput').value = originInput.value.trim();
                document.getElementById('nearbyForm').submit();
            },
            (error) => {
                document.getElementById('manualLocationInput').value = originInput.value.trim();
                document.getElementById('nearbyForm').submit();
            }
        );
    } else {
        document.getElementById('manualLocationInput').value = originInput.value.trim();
        document.getElementById('nearbyForm').submit();
    }
}

let selectedStationName = '';

function viewStationDetails(placeId) {
    fetch(`/transport/station/${placeId}`)
        .then(response => response.json())
        .then(res => {
            if (!res.success) {
                alert({{ Illuminate\Support\Js::from(__('transport.load_failed')) }});
                return;
            }

            const data = res.data;
            selectedStationName = data.name;

            // Fill basic info
            document.getElementById('detail-station-name').innerText = data.name;
            document.getElementById('detail-station-address').innerText = data.address;
            document.getElementById('detail-wheelchair').innerText = data.wheelchair;
            document.getElementById('detail-phone').innerText = data.phone;
            const ratingReviews = {{ Illuminate\Support\Js::from(__('messages.transport_rating_reviews', ['count' => '__COUNT__'])) }}
                .replace('__COUNT__', data.user_ratings_total);
            document.getElementById('detail-rating').innerText = `${data.rating} ★ (${ratingReviews})`;
            document.getElementById('detail-maps-link').href = data.google_maps_url;

            // Open/Closed Status
            const statusEl = document.getElementById('detail-status');
            if (data.is_open_now === true) {
                statusEl.innerText = {{ Illuminate\Support\Js::from(__('transport.open_now')) }};
                statusEl.style.color = '#2d6a4f';
            } else if (data.is_open_now === false) {
                statusEl.innerText = {{ Illuminate\Support\Js::from(__('transport.closed')) }};
                statusEl.style.color = '#d90429';
            } else {
                statusEl.innerText = 'N/A';
                statusEl.style.color = '#555';
            }

            // Render Operating Hours
            const hoursList = document.getElementById('detail-opening-hours');
            hoursList.innerHTML = '';
            data.opening_hours.forEach(day => {
                const li = document.createElement('li');
                li.innerText = day;
                hoursList.appendChild(li);
            });

            // Render Reviews
            const reviewsContainer = document.getElementById('detail-reviews-list');
            reviewsContainer.innerHTML = '';
            if (data.reviews && data.reviews.length > 0) {
                data.reviews.forEach(review => {
                    const div = document.createElement('div');
                    div.className = 'review-card';
                    div.innerHTML = `
                        <strong>${review.author_name}</strong> (${review.rating} ★)
                        <p>${review.text}</p>
                    `;
                    reviewsContainer.appendChild(div);
                });
            } else {
                reviewsContainer.innerHTML = '<p>' + {{ Illuminate\Support\Js::from(__('transport.no_reviews')) }} + '</p>';
            }

            // Display Modal
            document.getElementById('station-details-modal').style.display = 'flex';
        })
        .catch(err => console.error('Error fetching station details:', err));
}

function closeStationDetails() {
    document.getElementById('station-details-modal').style.display = 'none';
}

function setAsOriginFromModal() {
    const originInput = document.getElementById('origin-input');
    if (originInput) {
        originInput.value = selectedStationName;
    }
    closeStationDetails();
}

function initAutocomplete() {
    const originInput = document.getElementById("origin-input");
    const destinationInput = document.getElementById("destination-input");
    
    if (!originInput || !destinationInput) return;

    const options = {
        componentRestrictions: { country: "my" },
        fields: ["formatted_address", "geometry", "name"],
    };

    if (window.google && google.maps && google.maps.places) {
        new google.maps.places.Autocomplete(originInput, options);
        new google.maps.places.Autocomplete(destinationInput, options);
    }
}

function swapLocations() {
    const originInput = document.getElementById('origin-input');
    const destinationInput = document.getElementById('destination-input');

    if (originInput && destinationInput) {
        // Swap values
        const temp = originInput.value;
        originInput.value = destinationInput.value;
        destinationInput.value = temp;

        // Visual feedback (small button rotation)
        const swapBtn = document.querySelector('.btn-swap');
        if (swapBtn) {
            swapBtn.style.transform = swapBtn.style.transform === 'rotate(180deg)' ? 'rotate(0deg)' : 'rotate(180deg)';
        }
    }
}

function fetchNearbyStations() {
    const originInput = document.getElementById('origin-input');
    const originValue = originInput ? originInput.value.trim() : '';

    // If starting point has text (e.g., "TARUMT"), search using that location text
    if (originValue !== '') {
        window.location.href = `/transport/nearby?origin=${encodeURIComponent(originValue)}&t=${new Date().getTime()}`;
        return;
    }

    // If starting point input is empty, fallback to browser GPS location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                window.location.href = `/transport/nearby?lat=${lat}&lng=${lng}&t=${new Date().getTime()}`;
            },
            (error) => {
                alert(transportTranslations.gpsFailed);
            }
        );
    } else {
        alert(transportTranslations.gpsUnsupported);
    }
}

function sortRoutes(criterion) {
    const container = document.getElementById('routes-container');
    if (!container) return;

    // Convert cards NodeList into array for client-side sorting
    const cards = Array.from(container.getElementsByClassName('route-card'));

    cards.sort((a, b) => {
        let valA = parseFloat(a.getAttribute(`data-${criterion}`)) || 0;
        let valB = parseFloat(b.getAttribute(`data-${criterion}`)) || 0;
        return valA - valB;
    });

    // Re-append sorted card elements and dynamically update "Option 1, Option 2..." titles
    cards.forEach((card, index) => {
        container.appendChild(card);
        const titleEl = card.querySelector('.option-title');
        if (titleEl) {
            const timeSpan = card.querySelector('.step-desc');
            const fullTitleText = titleEl.textContent;
            const timePart = fullTitleText.split(': ')[1] || '';
            titleEl.textContent = `${transportTranslations.option.replace(':number', index + 1)} ${timePart}`;
        }
    });
}

function exportRoute(index, button) {
    // 1. Expand step-by-step directions for this card
    const detailPanel = document.getElementById('route-details-' + index);
    if (detailPanel) {
        detailPanel.classList.remove('timeline-hidden');
    }

    // 2. Add 'print-active' class exclusively to the selected option
    const cards = document.querySelectorAll('.route-card');
    cards.forEach((card, i) => {
        if (i === index) {
            card.classList.add('print-active');
        } else {
            card.classList.remove('print-active');
        }
    });

    // 3. Queue the guidance reward from the transportation export only.
    const rewardUrl = button?.dataset.rewardUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (rewardUrl && csrfToken) {
        fetch(rewardUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ activity: 'export_guidance' }),
        })
            .then(response => {
                if (!response.ok) throw new Error('Unable to queue guidance reward.');
                return response.json();
            })
            .then(payload => {
                if (payload.queued) {
                    document.querySelector('[data-reward-dot]')?.removeAttribute('hidden');
                }
            })
            .catch(error => console.error('Unable to queue guidance reward.', error));
    }

    // 4. Allow the expanded route and print styles to render before preview opens.
    setTimeout(() => {
        window.print();
    }, 50);

    // 5. Clean up classes after printing window closes
    window.onafterprint = () => {
        cards.forEach(card => card.classList.remove('print-active'));
    };
}

</script>

<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places&callback=initAutocomplete" async defer></script>

@include('components.footer')

</body>
</html>
