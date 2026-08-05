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

        if (!in_array($mode, ['transit', 'walking'])) {
            $mode = 'transit';
        }

        if (empty($origin) || empty($destination)) {
            return redirect()->back()->withInput()->with('error', 'Please enter both origin and destination.');
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
            $errorMessage = $data['error_message'] ?? 'Unable to find route between these locations.';
            return view('transport', compact('origin', 'destination', 'mode'))->with('error', $errorMessage);
        }

        $apiRoutes = $data['routes'] ?? [];
        $routes = [];

        foreach ($apiRoutes as $route) {
            $leg = $route['legs'][0];
            $steps = [];
            $legsSummary = [];
            $totalCalculatedFare = 0.0;
        
        $transitStepCount = 0;
        $previousTransitLine = null;

        foreach ($leg['steps'] as $index => $step) {
            $travelMode = $step['travel_mode'];
            $instructions = strip_tags($step['html_instructions']);
            $distanceText = $step['distance']['text'] ?? '0 km';
            $distanceMeters = $step['distance']['value'] ?? 0;

            if ($travelMode === 'TRANSIT') {
                $transitStepCount++;
                $transitDetails = $step['transit_details'];
                $lineName = $transitDetails['line']['short_name'] ?? $transitDetails['line']['name'] ?? 'Transit Line';
                $vehicleType = strtolower($transitDetails['line']['vehicle']['type'] ?? '');
                $isBus = str_contains($vehicleType, 'bus');
                $icon = $isBus ? '🚌' : '🚆';

                $legsSummary[] = $lineName;

                // Detect if this step is a Transfer Station
                $isTransfer = ($previousTransitLine !== null && $previousTransitLine !== $lineName);
                $previousTransitLine = $lineName;

                // Estimate segment fare (MYR) based on vehicle type and distance
                if ($isBus) {
                    // Standard RapidKL Bus Flat/Tier Fare Estimate
                    $segmentFare = ($distanceMeters > 10000) ? 2.50 : 1.00;
                } else {
                    // Rail Fare Tier Estimate (LRT/MRT/Monorail)
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
                    'dep_station' => $transitDetails['departure_stop']['name'] ?? 'Departure Station',
                    'arr_station' => $transitDetails['arrival_stop']['name'] ?? 'Arrival Station',
                    'num_stops' => $transitDetails['num_stops'] ?? 0,
                    'instructions' => "Board at {$transitDetails['departure_stop']['name']} → Ride {$transitDetails['num_stops']} stops → Alight at {$transitDetails['arrival_stop']['name']}.",
                    'distance' => $distanceText,
                    'fare' => number_format($segmentFare, 2),
                ];
            } elseif ($travelMode === 'WALKING') {
                $steps[] = [
                    'type' => 'walking',
                    'icon' => '🚶',
                    'is_transfer' => false,
                    'title' => 'Walk',
                    'instructions' => $instructions . " ({$distanceText}, approx. {$step['duration']['text']})",
                    'distance' => $distanceText,
                    'fare' => '0.00',
                ];
            }
        }

        // Use Google API total fare if provided, otherwise sum individual segment fares
        $apiFare = $route['fare']['value'] ?? null;
        $finalTotalFare = ($mode === 'transit') ? ($apiFare ?? $totalCalculatedFare) : 0;

        $routes[] = [
            'mode' => $mode,
            'duration' => $leg['duration']['text'],
            'distance' => $leg['distance']['text'],
            'total_fare' => ($mode === 'transit') ? number_format($finalTotalFare, 2) : 'Free',
            'legs_summary' => array_unique($legsSummary),
            'steps' => $steps,
        ];
    }

    return view('transport', compact('routes', 'origin', 'destination', 'mode'));
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
     * View Nearby Stations using Places API
     */
    public function nearbyStations(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $manualLocation = trim($request->input('manual_location', ''));
        $apiKey = config('services.google.maps_api_key');

        if ((!$lat || !$lng) && empty($manualLocation)) {
            return redirect()->route('transport.index')
                ->with('error', 'Please enter a starting location to find nearby stations.');
        }


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
                $origin = $manualLocation;
                return view('transport', compact('origin'))
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
        $origin = $manualLocation;

        if (empty($places)) {
            return view('transport', compact('origin'))->with('info', 'No public transport stations found near this location.');
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