<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Transport Route Search - ExploreMy</title>
    <link rel="stylesheet" href="{{ asset('css/transport.css') }}">
    <style>
        /* Lightweight CSS styling for Mode Selector */
        .mode-toggle-group {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }
        .mode-btn-option {
            flex: 1;
            padding: 10px;
            border: 2px solid #2d6a4f;
            border-radius: 8px;
            background: #ffffff;
            color: #2d6a4f;
            font-weight: bold;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
        }
        .mode-btn-option input[type="radio"] {
            display: none;
        }
        .mode-btn-option.active {
            background: #2d6a4f;
            color: #ffffff;
        }
    </style>
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

    @if(session('info'))
        <div class="alert alert-info">
            ℹ️ {{ session('info') }}
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
                
                <!-- Travel Mode Selector Toggle (Transit vs Walking) -->
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

                <!-- Connecting Line -->
                <div class="gmaps-connector">
                    <span class="connector-dots">⋮</span>
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

                <button type="submit" class="gmaps-btn">Search Route</button>
            </form>

            <!-- Trigger for View Nearby Stations -->
            <button type="button" class="btn-nearby" onclick="findNearbyStations()">
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
                        <div class="station-name">🚉 {{ $station['name'] }}</div>
                        <div class="step-desc">📍 {{ $station['vicinity'] }}</div>
                        <div class="station-distance">📏 Distance: <strong>{{ $station['distance'] }}</strong></div>
                        <button onclick="viewStationDetails('{{ $station['place_id'] }}')" class="btn-toggle-route" style="margin-top:8px;">
                            View Details
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Section 4: Available Routes / Directions Results -->
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

                    <!-- Transit Line Badges (if Transit Mode) -->
                    @if(!empty($route['legs_summary']))
                        <div class="transport-modes">
                            @foreach($route['legs_summary'] as $badge)
                                <span class="mode-btn active">
                                    {{ $badge }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <!-- Route Details Toggle Button -->
                    <button class="btn-toggle-route" onclick="showRouteDetails({{ $index }})">
                        {{ ($mode ?? 'transit') === 'walking' ? 'Select Route & View Walking Steps 👇' : 'Select Route & View Step-by-Step Directions 👇' }}
                    </button>

                    <!-- Detailed Itinerary Steps -->
                    <div id="route-details-{{ $index }}" class="timeline timeline-hidden">
                        @foreach($route['steps'] as $step)
                            <div class="timeline-step">
                                <div class="step-title">{{ $step['icon'] }} {{ $step['title'] }}</div>
                                <div class="step-desc">{{ $step['instructions'] }}</div>
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

// Find Nearby Stations (Enforces Starting Location)
function findNearbyStations() {
    const originInput = document.getElementById('origin-input');
    
    // Check if the starting location input is empty
    if (!originInput || originInput.value.trim() === '') {
        alert("Please enter a starting location before searching for nearby stations!");
        //ensure unput field become active
        setTimeout(() => {
            originInput.focus();
            originInput.style.border = "2px solid #e63946";
        }, 100);

        return false; //stop execution completely
    }

    originInput.style.border = ""; //reset input border styling if valid

    // Proceed to GPS/Geolocation if starting location is filled
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                document.getElementById('latInput').value = position.coords.latitude;
                document.getElementById('lngInput').value = position.coords.longitude;
                document.getElementById('nearbyForm').submit();
            },
            (error) => {
                // If location permission is denied, use the starting location input as fallback
                document.getElementById('manualLocationInput').value = originInput.value.trim();
                document.getElementById('nearbyForm').submit();
            }
        );
    } else {
        // Fallback for unsupported browsers
        document.getElementById('manualLocationInput').value = originInput.value.trim();
        document.getElementById('nearbyForm').submit();
    }
}

function viewStationDetails(placeId) {
    fetch(`/transport/station/${placeId}`)
        .then(res => res.json())
        .then(data => {
            alert(`Station Name: ${data.displayName?.text || 'N/A'}\nAddress: ${data.formattedAddress || 'N/A'}\nRating: ${data.rating || 'N/A'}`);
        })
        .catch(err => alert("Unable to load station details."));
}

function initAutocomplete() {
    const options = {
        componentRestrictions: { country: "my" },
        fields: ["formatted_address", "geometry", "name"],
    };

    const originInput = document.getElementById("origin-input");
    const destinationInput = document.getElementById("destination-input");

    if (originInput && destinationInput && typeof google !== 'undefined' && google.maps && google.maps.places) {
        new google.maps.places.Autocomplete(originInput, options);
        new google.maps.places.Autocomplete(destinationInput, options);
    }
}
</script>

<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&libraries=places&callback=initAutocomplete" async defer></script>

</body>
</html>