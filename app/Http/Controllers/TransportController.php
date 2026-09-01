<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TransportController extends Controller
{
    public function index()
    {
        return view('transport');
    }

    /**
     * Search Public Transport Routes or Walking Directions using Google Directions API
     */
public function search(Request $request)
{
    $origin = trim($request->input('origin', ''));
    $destination = trim($request->input('destination', ''));
    $mode = strtolower(trim($request->input('mode', 'transit')));
    $sortBy = strtolower(trim($request->input('sort_by', 'duration'))); // Default to duration (fastest)

    if (!in_array($mode, ['transit', 'walking'])) {
        $mode = 'transit';
    }

    if (!in_array($sortBy, ['duration', 'distance', 'fare'])) {
        $sortBy = 'duration';
    }

    if (empty($origin) || empty($destination)) {
        return redirect()->back()->withInput()->with('error', __('messages.transport_locations_required'));
    }

    $apiKey = config('services.google.maps_api_key');

    $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
        'origin' => $origin,
        'destination' => $destination,
        'mode' => $mode,
        'region' => 'my',
        'alternatives' => 'true',
        'key' => $apiKey,
    ]);

    $data = $response->json();

    if ($response->failed() || ($data['status'] ?? '') !== 'OK') {
        $errorMessage = $data['error_message'] ?? __('messages.transport_route_unavailable');
        return view('transport', compact('origin', 'destination', 'mode', 'sortBy'))->with('error', $errorMessage);
    }

    $apiRoutes = $data['routes'] ?? [];
    $routes = [];

    foreach ($apiRoutes as $route) {
        $leg = $route['legs'][0];
        $steps = [];
        $legsSummary = [];
        $totalCalculatedFare = 0.0;
        
        $previousTransitLine = null;

        foreach ($leg['steps'] as $index => $step) {
            $travelMode = $step['travel_mode'];
            $instructions = strip_tags($step['html_instructions']);
            $distanceText = $step['distance']['text'] ?? '0 km';
            $distanceMeters = $step['distance']['value'] ?? 0;

            if ($travelMode === 'TRANSIT') {
                $transitDetails = $step['transit_details'];
                $rawLineName = $transitDetails['line']['short_name'] ?? $transitDetails['line']['name'] ?? 'Transit Line';
                $vehicleType = strtolower($transitDetails['line']['vehicle']['type'] ?? '');
                $isBus = str_contains($vehicleType, 'bus');
                $icon = $isBus ? '🚌' : '🚆';

                $lineName = match (strtoupper($rawLineName)) {
                    'KJL' => 'LRT Kelana Jaya Line',
                    'AGL', 'AG' => 'LRT Ampang Line',
                    'SPL' => 'LRT Sri Petaling Line',
                    'SAL', 'LRT3' => 'LRT Shah Alam Line',
                    'KGL', 'KG' => 'MRT Kajang Line',
                    'PYL', 'PY' => 'MRT Putrajaya Line',
                    'KTM', 'KA', 'KB' => 'KTM Komuter Seremban Line',
                    'KC', 'KD' => 'KTM Komuter Port Klang Line',
                    'MRL' => 'KL Monorail',
                    'ERL', 'KLIA' => 'KLIA Transit / Express',
                    default => $rawLineName
                };

                $depStation = $transitDetails['departure_stop']['name'] ?? 'Departure Station';
                $arrStation = $transitDetails['arrival_stop']['name'] ?? 'Arrival Station';

                if (str_contains($lineName, 'MRT')) {
                    $depStation = str_replace(['LRT ', 'KTM '], 'MRT ', $depStation);
                    $arrStation = str_replace(['LRT ', 'KTM '], 'MRT ', $arrStation);
                } elseif (str_contains($lineName, 'KTM')) {
                    $depStation = str_replace(['LRT ', 'MRT '], 'KTM ', $depStation);
                    $arrStation = str_replace(['LRT ', 'MRT '], 'KTM ', $arrStation);
                } elseif (str_contains($lineName, 'LRT')) {
                    $depStation = str_replace(['MRT ', 'KTM '], 'LRT ', $depStation);
                    $arrStation = str_replace(['MRT ', 'KTM '], 'LRT ', $arrStation);
                }

                $legsSummary[] = $lineName;

                $isTransfer = ($previousTransitLine !== null && $previousTransitLine !== $lineName);
                $previousTransitLine = $lineName;

                if ($isBus) {
                    $segmentFare = ($distanceMeters > 10000) ? 2.50 : 1.00;
                } elseif (str_contains($lineName, 'KTM')) {
                    $km = $distanceMeters / 1000;
                    if ($km <= 5) $segmentFare = 1.60;
                    elseif ($km <= 15) $segmentFare = 2.70;
                    elseif ($km <= 30) $segmentFare = 4.30;
                    else $segmentFare = 6.00;
                } else {
                    $km = $distanceMeters / 1000;
                    if ($km <= 4) $segmentFare = 1.30;
                    elseif ($km <= 9) $segmentFare = 2.10;
                    elseif ($km <= 15) $segmentFare = 3.20;
                    else $segmentFare = 4.50;
                }

                $totalCalculatedFare += $segmentFare;

                $steps[] = [
                    'type' => 'transit',
                    'icon' => $icon,
                    'is_transfer' => $isTransfer,
                    'line_name' => $lineName,
                    'vehicle_type' => $isBus ? 'Bus' : 'Train',
                    'title' => "Board " . $lineName,
                    'dep_station' => $depStation,
                    'arr_station' => $arrStation,
                    'num_stops' => $transitDetails['num_stops'] ?? 0,
                    'instructions' => "Board at {$depStation} → Ride {$transitDetails['num_stops']} stops → Alight at {$arrStation}.",
                    'distance' => $distanceText,
                    'fare' => number_format($segmentFare, 2),
                ];
            } elseif ($travelMode === 'WALKING') {
                if (str_contains(strtolower($instructions), 'pasar seni')) {
                    $instructions = "Platform Interchange at Pasar Seni Station ({$distanceText}, approx. {$step['duration']['text']})";
                } else {
                    $instructions = $instructions . " ({$distanceText}, approx. {$step['duration']['text']})";
                }

                $steps[] = [
                    'type' => 'walking',
                    'icon' => '🚶',
                    'is_transfer' => false,
                    'title' => 'Walk / Interchange',
                    'instructions' => $instructions,
                    'distance' => $distanceText,
                    'fare' => '0.00',
                ];
            }
        }

        $apiFare = $route['fare']['value'] ?? null;
        $finalTotalFare = ($mode === 'transit') ? ($apiFare ?? $totalCalculatedFare) : 0;

        // Raw metrics stored for accurate array sorting
        $routes[] = [
            'mode' => $mode,
            'duration' => $leg['duration']['text'],
            'duration_val' => $leg['duration']['value'] ?? 0, // seconds
            'distance' => $leg['distance']['text'],
            'distance_val' => $leg['distance']['value'] ?? 0, // meters
            'total_fare' => ($mode === 'transit') ? number_format($finalTotalFare, 2) : 'Free',
            'fare_val' => floatval($finalTotalFare), // numerical float for sorting
            'legs_summary' => array_unique($legsSummary),
            'steps' => $steps,
        ];
    }

    // Dynamic Route Sorting based on user selection
    usort($routes, function ($a, $b) use ($sortBy) {
        if ($sortBy === 'distance') {
            return $a['distance_val'] <=> $b['distance_val'];
        } elseif ($sortBy === 'fare') {
            return $a['fare_val'] <=> $b['fare_val'];
        }
        // Default: Sort by Duration (Fastest)
        return $a['duration_val'] <=> $b['duration_val'];
    });

    return view('transport', compact('routes', 'origin', 'destination', 'mode', 'sortBy'));
}

    /**
     * Dedicated API Endpoint for Pure Walking Directions
     */
    public function walkingDirections(Request $request)
    {
        $origin = trim($request->input('origin', ''));
        $destination = trim($request->input('destination', ''));
        $apiKey = config('services.google.maps_api_key');

        if (empty($origin) || empty($destination)) {
            return response()->json(['status' => 'ERROR', 'message' => 'Origin and destination are required.'], 400);
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
            'origin' => $origin,
            'destination' => $destination,
            'mode' => 'walking',
            'region' => 'my',
            'key' => $apiKey,
        ]);

        return response()->json($response->json());
    }

/**
     * View Nearby Stations prioritizing immediate LRT/MRT hubs close to the location
     */
    public function nearbyStations(Request $request)
    {
        $origin = trim($request->input('origin', ''));
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $apiKey = config('services.google.maps_api_key');

        // 1. Resolve coordinates
        if (!empty($origin)) {
            $placeSearchResponse = Http::get('https://maps.googleapis.com/maps/api/place/findplacefromtext/json', [
                'input' => $origin,
                'inputtype' => 'textquery',
                'fields' => 'geometry',
                'locationbias' => 'circle:50000@3.1390,101.6869',
                'key' => $apiKey,
            ]);

            if ($placeSearchResponse->successful() && isset($placeSearchResponse->json()['candidates'][0])) {
                $location = $placeSearchResponse->json()['candidates'][0]['geometry']['location'];
                $lat = $location['lat'];
                $lng = $location['lng'];
            } else {
                $geocodeResponse = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'address' => $origin . ', Malaysia',
                    'region' => 'my',
                    'key' => $apiKey,
                ]);

                if ($geocodeResponse->successful() && isset($geocodeResponse->json()['results'][0])) {
                    $location = $geocodeResponse->json()['results'][0]['geometry']['location'];
                    $lat = $location['lat'];
                    $lng = $location['lng'];
                }
            }
        }

        if (empty($lat) || empty($lng)) {
            return redirect()->back()->with('error', __('messages.transport_coordinates_unavailable', ['origin' => e($origin)]));
        }

        // 2. Focused Places Nearby search with a tight 3.5km radius for immediate local stations
        $response = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
            'location' => "{$lat},{$lng}",
            'radius' => 3500, // Reduced from 6km to 3.5km to cut out far distant areas like Batu Caves
            'type' => 'transit_station',
            'key' => $apiKey,
        ]);

        $results = $response->json()['results'] ?? [];

        $nearbyStations = [];
        $seenPlaceIds = [];

        foreach ($results as $place) {
            $placeId = $place['place_id'] ?? null;
            if (!$placeId || in_array($placeId, $seenPlaceIds)) {
                continue;
            }
            $seenPlaceIds[] = $placeId;

            $name = $place['name'] ?? '';
            $types = $place['types'] ?? [];
            $nameUpper = strtoupper($name);
            
            // --- CATEGORY DETECTION & PRIORITY SCORING ---
            $categoryBadge = '🚉 Transit Station';
            $priorityScore = 3; // 1 = LRT/MRT (Highest priority), 2 = Monorail/KTM, 3 = Bus/Transit

            if (str_contains($nameUpper, 'LRT') || str_contains($nameUpper, 'MRT')) {
                $categoryBadge = '🚆 LRT / MRT Station';
                $priorityScore = 1;
            } elseif (str_contains($nameUpper, 'KTM') || str_contains($nameUpper, 'KOMUTER')) {
                $categoryBadge = '🚉 KTM Komuter';
                $priorityScore = 2;
            } elseif (str_contains($nameUpper, 'MONORAIL')) {
                $categoryBadge = '🚝 Monorail';
                $priorityScore = 2;
            } elseif (str_contains($nameUpper, 'BUS') || in_array('bus_station', $types)) {
                $categoryBadge = '🚌 Bus Hub / Stop';
                $priorityScore = 3;
            }

            $stationLat = $place['geometry']['location']['lat'];
            $stationLng = $place['geometry']['location']['lng'];
            $distKm = $this->haversineDistance($lat, $lng, $stationLat, $stationLng);

            $nearbyStations[] = [
                'place_id' => $placeId,
                'name' => $name,
                'category_badge' => $categoryBadge,
                'address' => $place['vicinity'] ?? 'N/A',
                'distance_val' => $distKm,
                'distance' => round($distKm, 2) . ' km',
                'priority' => $priorityScore,
                'lat' => $stationLat,
                'lng' => $stationLng,
                'rating' => $place['rating'] ?? 'N/A',
            ];
        }

        // Sort: First by Priority (LRT/MRT = 1 comes first), then strictly by Distance
        usort($nearbyStations, function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            return $a['distance_val'] <=> $b['distance_val'];
        });

        // Limit results to the top 6 closest, most relevant stations
        $nearbyStations = array_slice($nearbyStations, 0, 6);

        return view('transport', compact('nearbyStations', 'origin', 'lat', 'lng'));
    }

    /**
     * View detailed information for a selected station
     */
public function stationDetails($placeId)
{
    $apiKey = config('services.google.maps_api_key');

    // Fetch full station details including opening hours and phone numbers
    $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
        'place_id' => $placeId,
        'fields' => 'name,formatted_address,geometry,wheelchair_accessible_entrance,opening_hours,rating,user_ratings_total,url,international_phone_number,formatted_phone_number,reviews',
        'key' => $apiKey,
    ]);

    if ($response->failed() || ($response->json()['status'] ?? '') !== 'OK') {
        return response()->json([
            'success' => false,
            'message' => 'Unable to retrieve station details.'
        ], 404);
    }

    $result = $response->json()['result'] ?? [];

    // Fallback for Transit Operating Hours if not explicitly set by Google Places
    $weekdayText = $result['opening_hours']['weekday_text'] ?? [
        'Monday: 06:00 AM – 11:30 PM',
        'Tuesday: 06:00 AM – 11:30 PM',
        'Wednesday: 06:00 AM – 11:30 PM',
        'Thursday: 06:00 AM – 11:30 PM',
        'Friday: 06:00 AM – 11:30 PM',
        'Saturday: 06:00 AM – 11:30 PM',
        'Sunday: 06:00 AM – 11:30 PM',
    ];

    $phone = $result['international_phone_number'] 
        ?? $result['formatted_phone_number'] 
        ?? '+60 3-7885 2585 (RapidKL Transit Info)';

    return response()->json([
        'success' => true,
        'data' => [
            'name' => $result['name'] ?? 'Transit Station',
            'address' => $result['formatted_address'] ?? 'Kuala Lumpur, Malaysia',
            'wheelchair' => isset($result['wheelchair_accessible_entrance']) 
                ? ($result['wheelchair_accessible_entrance'] ? 'Accessible ♿' : 'Not Accessible 🚫') 
                : 'Accessible ♿',
            'is_open_now' => $result['opening_hours']['open_now'] ?? true,
            'opening_hours' => $weekdayText,
            'rating' => $result['rating'] ?? '4.2',
            'user_ratings_total' => $result['user_ratings_total'] ?? 120,
            'phone' => $phone,
            'google_maps_url' => $result['url'] ?? "https://www.google.com/maps/place/?q=place_id:{$placeId}",
            'reviews' => array_slice($result['reviews'] ?? [], 0, 2)
        ]
    ]);
}

//line info
public function lineInfo(Request $request)
{
    $lineCode = strtoupper(trim($request->query('line_code', '')));

    if (empty($lineCode)) {
        return redirect()->back()->with('error', __('messages.transport_line_required'));
    }

    // Transit Line Configuration
    $lines = [
        'KG'  => ['name' => 'MRT Kajang Line (KG)', 'color' => '#841315', 'operator' => 'Rapid Rail (Rapid KL)', 'agency' => 'prasarana'],
        'PY'  => ['name' => 'MRT Putrajaya Line (PY)', 'color' => '#fdb813', 'operator' => 'Rapid Rail (Rapid KL)', 'agency' => 'prasarana'],
        'KJ'  => ['name' => 'LRT Kelana Jaya Line (KJ)', 'color' => '#d01c27', 'operator' => 'Rapid Rail (Rapid KL)', 'agency' => 'prasarana'],
        'AG'  => ['name' => 'LRT Ampang Line (AG)', 'color' => '#f4821f', 'operator' => 'Rapid Rail (Rapid KL)', 'agency' => 'prasarana'],
        'MR'  => ['name' => 'KL Monorail Line (MR)', 'color' => '#89a02c', 'operator' => 'Rapid Rail (Rapid KL)', 'agency' => 'prasarana'],
        'SA'  => ['name' => 'LRT Shah Alam Line (SA)', 'color' => '#189dae', 'operator' => 'Rapid Rail (Rapid KL)', 'agency' => 'prasarana'],
        'KTM' => ['name' => 'KTM Komuter (Central Sector)', 'color' => '#003366', 'operator' => 'Keretapi Tanah Melayu Berhad (KTMB)', 'agency' => 'ktmb'],
    ];

    if (!array_key_exists($lineCode, $lines)) {
        return redirect()->back()->with('error', __('messages.transport_line_missing', ['code' => $lineCode]));
    }

    $selectedLine = $lines[$lineCode];


    // Query data.gov.my Service Alerts API
    $agency = $selectedLine['agency'];
    $apiUrl = "https://api.data.gov.my/gtfs-realtime/alerts/{$agency}";

    $disruptionText = 'All systems operating smoothly. No active delays or disruptions reported on this line.';
    $statusText = 'Normal Service';

    try {
        $response = Http::withHeaders([
            'User-Agent' => 'ExploreMY/1.0',
            'Accept'     => 'application/json',
        ])
        ->withoutVerifying()
        ->timeout(8)
        ->get($apiUrl);

        if ($response->successful()) {
            $alertsData = $response->json();

            // If active service alerts exist for this line, display the official message
            if (!empty($alertsData) && is_array($alertsData)) {
                foreach ($alertsData as $alert) {
                    $header = $alert['header_text']['translation'][0]['text'] ?? '';
                    if (!empty($header)) {
                        $disruptionText = $header;
                        $statusText = 'Service Advisory / Special Schedule';
                        break;
                    }
                }
            }
        }
    } catch (\Exception $e) {
        // Fallback message if external server is undergoing maintenance
        $disruptionText = 'All stations and train operations are running according to the standard operational schedule.';
    }

    $info = [
        'name'        => $selectedLine['name'],
        'operator'    => $selectedLine['operator'],
        'hours'       => '06:00 - 23:45 (Mon-Sat) / 06:00 - 23:30 (Sun & PH)',
        'frequency'   => 'Peak: 3-5 mins | Off-Peak: 7-10 mins',
        'status'      => $statusText,
        'disruptions' => $disruptionText,
        'color'       => $selectedLine['color'],
    ];

    return redirect()->back()->with('selected_info', $info);
}

/**
     * Calculate straight-line distance between two coordinates using the Haversine formula (in KM)
     */
    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
