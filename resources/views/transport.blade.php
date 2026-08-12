<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Transport Route Search - ExploreMy</title>
    <link rel="stylesheet" href="{{ asset('css/transport.css') }}">
</head>
<body>

<div class="transport-container">

    <!-- Top Header -->
    <header class="nav-header">
        <div class="brand-section">
            <img src="{{ asset('images/ExploreMy_icon.jpeg') }}" alt="ExploreMY Logo" class="brand-logo">
            <h1>ExploreMY Transport</h1>
        </div>
        <span class="header-subtitle">Route Planner</span>
    </header>

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
            Operating Hours: {{ session('selected_info')['hours'] }}<br>
            Status: {{ session('selected_info')['status'] }}
        </div>
    @endif

    <!-- Section 1: Route Search Form & Nearby Search -->
    <div class="card-panel">
        <h2 class="card-title">Search Transport & Walking Directions</h2>
        
        <div class="gmaps-card">
            <form action="{{ route('transport.search') }}" method="GET" id="search-form">
                
                <!-- Travel Mode Selector Toggle -->
                <div class="mode-toggle-group">
                    <label class="mode-btn-option {{ ($mode ?? 'transit') === 'transit' ? 'active' : '' }}" onclick="selectMode(this)">
                        <input type="radio" name="mode" value="transit" {{ ($mode ?? 'transit') === 'transit' ? 'checked' : '' }}>
                        🚌 Step-by-Step Transit
                    </label>
                    <label class="mode-btn-option {{ ($mode ?? '') === 'walking' ? 'active' : '' }}" onclick="selectMode(this)">
                        <input type="radio" name="mode" value="walking" {{ ($mode ?? '') === 'walking' ? 'checked' : '' }}>
                        🚶 Walking Directions
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
                        placeholder="Choose starting point..." 
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
                        placeholder="Choose destination..." 
                        value="{{ old('destination', $destination ?? '') }}" 
                        required 
                        autocomplete="off">
                </div>
            </div>
                <!-- Connecting Line -->
                <div class="gmaps-connector">
                    <button type="button" class="btn-swap" onclick="swapLocations()" title="Swap Starting Location and Destination">
                        ⇅
                    </button>
                </div>
        </div>
                <button type="submit" class="gmaps-btn">Search Route</button>
            </form>

            <!-- Trigger for View Nearby Stations -->
            <button type="button" class="btn-nearby" onclick="fetchNearbyStations()">
                📍 View Nearby Stations
            </button>

            <!-- Hidden form for GPS coordinates -->
            <form id="nearbyForm" action="{{ route('transport.nearby') }}" method="GET" style="display:none;">
                <input type="hidden" name="lat" id="latInput">
                <input type="hidden" name="lng" id="lngInput">
                <input type="hidden" name="manual_location" id="manualLocationInput">
            </form>
        </div>
    </div>

    <!-- Section 2: Optional Transport Service Information Lookup -->
    <div class="card-panel">
        <div class="service-header" onclick="toggleServiceInfo()">
            <h2 class="card-title">ℹ️ Optional: Check Transport Line Info & Operating Hours</h2>
            <span id="toggle-icon">+</span>
        </div>

        <div id="service-info-panel" class="service-panel-hidden">
            <p class="service-panel-desc">Select a line to view service status, schedule, and operating hours:</p>
            <form action="{{ route('transport.line-info') }}" method="GET" class="service-form">
                <select name="line_code" class="form-control">
                    <option value="KG">MRT Kajang Line (KG)</option>
                    <option value="PY">MRT Putrajaya Line (PY)</option>
                    <option value="KJ">LRT Kelana Jaya Line (KJ)</option>
                    <option value="AG">LRT Ampang Line (AG)</option>
                    <option value="MR">KL Monorail Line (MR)</option>
                </select>
                <button type="submit" class="btn-service">Check Info</button>
            </form>
        </div>
    </div>

<!-- Section 3: Nearby Stations Results -->
    @if(isset($nearbyStations) && count($nearbyStations) > 0)
        <div class="card-panel">
            <h2 class="card-title">Nearby Public Transport Stations</h2>
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
                        <div class="station-distance">📏 Distance: <strong>{{ $station['distance'] }}</strong></div>
                        <button onclick="viewStationDetails('{{ $station['place_id'] }}')" class="btn-toggle-route" style="margin-top:8px;">
                            View Details
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
        
        <h3 id="detail-station-name">Station Name</h3>
        <p id="detail-station-address" class="station-address">Station Address</p>

        <div class="details-grid">
            <div class="detail-item">
                <span class="detail-label">♿ Wheelchair Access</span>
                <span id="detail-wheelchair" class="detail-value">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">🕒 Current Status</span>
                <span id="detail-status" class="detail-value">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">⭐ Rating</span>
                <span id="detail-rating" class="detail-value">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">📞 Phone</span>
                <span id="detail-phone" class="detail-value">-</span>
            </div>
        </div>

        <div class="hours-section">
            <h4>Operating Hours</h4>
            <ul id="detail-opening-hours"></ul>
        </div>

        <div id="reviews-section" class="reviews-section">
            <h4>Recent Reviews</h4>
            <div id="detail-reviews-list"></div>
        </div>

        <div class="modal-actions">
            <a id="detail-maps-link" href="#" target="_blank" class="btn-primary-modal">View on Google Maps 🗺️</a>
            <button type="button" class="btn-secondary-modal" onclick="setAsOriginFromModal()">Set as Starting Point 📍</button>
        </div>
    </div>
</div>

<!-- Section 4: Route Results -->
@if(isset($routes) && count($routes) > 0)
    <div class="card-panel">
        <h2 class="card-title">
            {{ ($mode ?? 'transit') === 'walking' ? '🚶 Walking Routes' : '🚌 Public Transport Routes' }} 
            ({{ count($routes) }} options found)
        </h2>

        @foreach($routes as $index => $route)
            <div class="station-item" style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color:#1b4332; font-size:16px;">Option {{ $index + 1 }}: {{ $route['duration'] }}</strong>
                        <span class="step-desc">({{ $route['distance'] }})</span>
                    </div>
                    <span class="fare-tag">
                        {{ $route['mode'] === 'walking' ? 'Cost: Free 🚶' : 'Total Fare: RM ' . $route['total_fare'] }}
                    </span>
                </div>

                @if(!empty($route['legs_summary']))
                    <div class="transport-modes" style="margin-top: 8px;">
                        @foreach($route['legs_summary'] as $badge)
                            <span class="mode-btn active">{{ $badge }}</span>
                        @endforeach
                    </div>
                @endif

                <button class="btn-toggle-route" onclick="showRouteDetails({{ $index }})" style="margin-top: 10px;">
                    Select Route & View Step-by-Step Directions 👇
                </button>

                <div id="route-details-{{ $index }}" class="timeline timeline-hidden" style="margin-top: 12px;">
                    @foreach($route['steps'] as $step)
                        <div class="timeline-step {{ !empty($step['is_transfer']) ? 'transfer-step' : '' }}">
                            <div class="step-title">
                                {{ $step['icon'] }} {{ $step['title'] }}
                                
                                <!-- 1. HIGHLIGHT TRANSFER STATION -->
                                @if(!empty($step['is_transfer']))
                                    <span class="transfer-badge">🔄 Transfer Station</span>
                                @endif

                                <!-- 2. SHOW SEGMENT/STATION FARE -->
                                <span class="step-fare-tag">
                                    {{ $step['type'] === 'walking' ? 'Free' : 'Fare: RM ' . $step['fare'] }}
                                </span>
                            </div>

                            <div class="step-desc" style="margin-top: 4px;">
                                {{ $step['instructions'] }}
                            </div>

                            @if($step['type'] === 'transit')
                                <div style="font-size: 12px; color: #555; margin-top: 4px;">
                                    📍 <strong>Board:</strong> {{ $step['dep_station'] }} <br>
                                    🏁 <strong>Alight:</strong> {{ $step['arr_station'] }} ({{ $step['num_stops'] }} stops, {{ $step['distance'] }})
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif

</div>

<script>
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
        alert("Please enter a starting location before searching for nearby stations!");
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
                alert('Could not load station details.');
                return;
            }

            const data = res.data;
            selectedStationName = data.name;

            // Fill basic info
            document.getElementById('detail-station-name').innerText = data.name;
            document.getElementById('detail-station-address').innerText = data.address;
            document.getElementById('detail-wheelchair').innerText = data.wheelchair;
            document.getElementById('detail-phone').innerText = data.phone;
            document.getElementById('detail-rating').innerText = `${data.rating} ★ (${data.user_ratings_total} reviews)`;
            document.getElementById('detail-maps-link').href = data.google_maps_url;

            // Open/Closed Status
            const statusEl = document.getElementById('detail-status');
            if (data.is_open_now === true) {
                statusEl.innerText = 'Open Now';
                statusEl.style.color = '#2d6a4f';
            } else if (data.is_open_now === false) {
                statusEl.innerText = 'Closed';
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
                reviewsContainer.innerHTML = '<p>No reviews available.</p>';
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
                alert('Unable to retrieve current location. Please type a starting point in the box.');
            }
        );
    } else {
        alert('Geolocation is not supported by your browser. Please type a starting point in the box.');
    }
}

</script>

<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places&callback=initAutocomplete" async defer></script>

</body>
</html>