<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RoutePlanningController extends Controller
{
    private const PLACES = [
        ['name' => 'Kuala Lumpur', 'latitude' => 3.1390, 'longitude' => 101.6869],
        ['name' => 'Putrajaya', 'latitude' => 2.9264, 'longitude' => 101.6964],
        ['name' => 'Melaka City', 'latitude' => 2.1896, 'longitude' => 102.2501],
        ['name' => 'Kuantan', 'latitude' => 3.8077, 'longitude' => 103.3260],
        ['name' => 'Ipoh', 'latitude' => 4.5975, 'longitude' => 101.0901],
        ['name' => 'George Town', 'latitude' => 5.4141, 'longitude' => 100.3288],
    ];

    public function index()
    {
        return view('route-planning.route');
    }

    public function storePreference(Request $request)
    {
        $validated = $request->validate([
            'optimization_preference' => [
                'required',
                'in:fastest,shortest,eco,lowest_cost',
            ],
        ]);

        if ($validated['optimization_preference'] !== 'shortest') {
            return back()->with('success', 'Preference selected: ' . $validated['optimization_preference']);
        }

        return back()
            ->with('success', 'Shortest route calculated successfully.')
            ->with('shortestRoute', $this->findShortestRoute(self::PLACES));
    }

    /** Find the exact shortest open route from the first place using Held-Karp. */
    private function findShortestRoute(array $places): array
    {
        $placeCount = count($places);
        $distances = [];

        for ($from = 0; $from < $placeCount; $from++) {
            for ($to = 0; $to < $placeCount; $to++) {
                $distances[$from][$to] = $this->haversineDistance($places[$from], $places[$to]);
            }
        }

        $states = ['1,0' => ['distance' => 0.0, 'path' => [0]]];
        $allVisitedMask = (1 << $placeCount) - 1;

        for ($mask = 1; $mask <= $allVisitedMask; $mask++) {
            for ($last = 0; $last < $placeCount; $last++) {
                $key = $mask . ',' . $last;
                if (!isset($states[$key])) {
                    continue;
                }

                for ($next = 1; $next < $placeCount; $next++) {
                    if (($mask & (1 << $next)) !== 0) {
                        continue;
                    }

                    $nextMask = $mask | (1 << $next);
                    $nextKey = $nextMask . ',' . $next;
                    $candidateDistance = $states[$key]['distance'] + $distances[$last][$next];

                    if (!isset($states[$nextKey]) || $candidateDistance < $states[$nextKey]['distance']) {
                        $states[$nextKey] = [
                            'distance' => $candidateDistance,
                            'path' => [...$states[$key]['path'], $next],
                        ];
                    }
                }
            }
        }

        $best = null;
        for ($last = 1; $last < $placeCount; $last++) {
            $state = $states[$allVisitedMask . ',' . $last] ?? null;
            if ($state !== null && ($best === null || $state['distance'] < $best['distance'])) {
                $best = $state;
            }
        }

        $stops = [];
        foreach ($best['path'] as $position => $placeIndex) {
            $stops[] = [
                'name' => $places[$placeIndex]['name'],
                'distance_from_previous' => $position === 0
                    ? 0.0
                    : round($distances[$best['path'][$position - 1]][$placeIndex], 2),
            ];
        }

        return ['stops' => $stops, 'total_distance' => round($best['distance'], 2)];
    }

    private function haversineDistance(array $from, array $to): float
    {
        $earthRadiusKm = 6371;
        $latitudeDifference = deg2rad($to['latitude'] - $from['latitude']);
        $longitudeDifference = deg2rad($to['longitude'] - $from['longitude']);
        $fromLatitude = deg2rad($from['latitude']);
        $toLatitude = deg2rad($to['latitude']);
        $a = sin($latitudeDifference / 2) ** 2
            + cos($fromLatitude) * cos($toLatitude)
            * sin($longitudeDifference / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
