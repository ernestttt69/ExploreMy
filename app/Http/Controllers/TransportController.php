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
        // Dynamic mode: defaults to 'transit' for step-by-step guidance, supports 'walking' for pedestrian directions
        $mode = strtolower(trim($request->input('mode', 'transit')));
        
        if (!in_array($mode, ['transit', 'walking'])) {
            $mode = 'transit';
        }

        // Check if starting location or destination is missing
        if (empty($origin) && empty($destination)) {
            return redirect()->route('transport.index')
                ->with('error', 'Please enter both a starting location and a destination.');
        }

        if (empty($origin)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Please enter a starting location.');
        }

        if (empty($destination)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Please enter a destination.');
        }

        // Check if inputs are too short to be valid locations
        if (strlen($origin) < 2 || strlen($destination) < 2) {
            return view('transport', compact('origin', 'destination', 'mode'))
                ->with('error', 'Location unrecognized. Please enter valid location names.');
        }

        $apiKey = config('services.google.maps_api_key');

        // Call Google Maps Directions API with the selected mode ('transit' or 'walking')
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
            $errorMessage = $data['error_message'] ?? 'Unable to find route between these locations. Please check location names.';
            return view('transport', compact('origin', 'destination', 'mode'))->with('error', $errorMessage);
        }

        $apiRoutes = $data['routes'] ?? [];
        $routes = [];

        foreach ($apiRoutes as $route) {
            $leg = $route['legs'][0];
            $steps = [];
            $legsSummary = [];
            $totalFare = $route['fare']['value'] ?? null;

            foreach ($leg['steps'] as $step) {
                $travelMode = $step['travel_mode'];
                $instructions = strip_tags($step['html_instructions']);

                if ($travelMode === 'TRANSIT') {
                    $transitDetails = $step['transit_details'];
                    $lineName = $transitDetails['line']['short_name'] ?? $transitDetails['line']['name'] ?? 'Transit Line';
                    $vehicleType = strtolower($transitDetails['line']['vehicle']['type'] ?? '');

                    $icon = str_contains($vehicleType, 'bus') ? '🚌' : '🚆';
                    $legsSummary[] = $lineName;

                    $steps[] = [
                        'type' => 'transit',
                        'icon' => $icon,
                        'title' => "Board " . $lineName,
                        'instructions' => "Board at {$transitDetails['departure_stop']['name']} → Ride {$transitDetails['num_stops']} stops → Alight at {$transitDetails['arrival_stop']['name']}.",
                    ];
                } elseif ($travelMode === 'WALKING') {
                    $steps[] = [
                        'type' => 'walking',
                        'icon' => '🚶',
                        'title' => 'Walk',
                        'instructions' => $instructions . " ({$step['distance']['text']}, approx. {$step['duration']['text']})",
                    ];
                }
            }

            $routes[] = [
                'mode' => $mode,
                'duration' => $leg['duration']['text'],
                'distance' => $leg['distance']['text'],
                'total_fare' => ($mode === 'transit' && $totalFare) ? number_format($totalFare, 2) : ($mode === 'transit' ? '3.50' : 'Free'),
                'legs_summary' => array_unique($legsSummary),
                'steps' => $steps,
            ];
        }

        return view('transport', compact('routes', 'origin', 'destination', 'mode'));
    }

    /**
     * Dedicated API Endpoint for Pure Walking Directions (AJAX / Json responses)
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
     * View Nearby Stations using Places API
     */
    public function nearbyStations(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $manualLocation = trim($request->input('manual_location', ''));
        $apiKey = config('services.google.maps_api_key');

        // Check if neither GPS coordinates nor a manual starting location was provided
        if ((!$lat || !$lng) && empty($manualLocation)) {
            return redirect()->route('transport.index')
                ->with('error', 'Please enter a starting location to find nearby stations.');
        }

        // Convert manual address input into coordinates if GPS is unavailable
        if ((!$lat || !$lng) && !empty($manualLocation)) {
            $geoRes = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $manualLocation,
                'key' => $apiKey,
            ]);

            if ($geoRes->successful() && !empty($geoRes->json()['results'])) {
                $locationData = $geoRes->json()['results'][0]['geometry']['location'];
                $lat = $locationData['lat'];
                $lng = $locationData['lng'];
            } else {
                return redirect()->route('transport.index')
                    ->with('error', 'Could not resolve starting location. Please enter a valid address.');
            }
        }

        // Call Google Places API searchNearby
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.types,places.rating',
        ])->post('https://places.googleapis.com/v1/places:searchNearby', [
            'includedTypes' => ['transit_station', 'bus_station', 'subway_station', 'train_station'],
            'maxResultCount' => 10,
            'locationRestriction' => [
                'circle' => [
                    'center' => [
                        'latitude' => (float)$lat,
                        'longitude' => (float)$lng,
                    ],
                    'radius' => 2000.0,
                ]
            ]
        ]);

        $places = $response->json()['places'] ?? [];

        if (empty($places)) {
            return redirect()->route('transport.index')->with('info', 'No public transport stations found near this location.');
        }

        $nearbyStations = [];
        foreach ($places as $place) {
            $stationLat = $place['location']['latitude'];
            $stationLng = $place['location']['longitude'];

            $distanceKm = round($this->haversineDistance($lat, $lng, $stationLat, $stationLng), 2);

            $nearbyStations[] = [
                'place_id' => $place['id'],
                'name' => $place['displayName']['text'] ?? 'Station',
                'vicinity' => $place['formattedAddress'] ?? 'N/A',
                'types' => implode(', ', $place['types'] ?? []),
                'distance' => "{$distanceKm} km",
                'rating' => $place['rating'] ?? 'N/A',
            ];
        }

        $origin = $manualLocation;

        return view('transport', compact('nearbyStations', 'origin'));
    }

    /**
     * View detailed information for a selected station
     */
    public function stationDetails($placeId)
    {
        $apiKey = config('services.google.maps_api_key');

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => 'id,displayName,formattedAddress,types,rating,nationalPhoneNumber,websiteUri',
        ])->get("https://places.googleapis.com/v1/places/{$placeId}");

        return response()->json($response->json());
    }

    public function lineInfo(Request $request)
    {
        $lineCode = $request->query('line_code');

        $lineDetails = [
            'KG' => ['name' => 'MRT Kajang Line', 'hours' => '06:00 - 23:30', 'status' => 'Normal Service'],
            'PY' => ['name' => 'MRT Putrajaya Line', 'hours' => '06:00 - 23:30', 'status' => 'Normal Service'],
            'KJ' => ['name' => 'LRT Kelana Jaya Line', 'hours' => '06:00 - 23:45', 'status' => 'Normal Service'],
            'AG' => ['name' => 'LRT Ampang Line', 'hours' => '06:00 - 23:30', 'status' => 'Normal Service'],
            'MR' => ['name' => 'KL Monorail Line', 'hours' => '06:00 - 23:30', 'status' => 'Normal Service'],
        ];

        $info = $lineDetails[$lineCode] ?? null;

        return redirect()->back()->with('selected_info', $info);
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;
        $dLat = deg2rad((float)$lat2 - (float)$lat1);
        $dLon = deg2rad((float)$lon2 - (float)$lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}