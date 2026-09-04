<?php

namespace App\Services;

class TransitFareEstimator
{
    public function estimate(string $lineName, string $vehicleType, int $distanceMeters): ?float
    {
        $line = strtoupper($lineName);
        $vehicle = strtoupper($vehicleType);

        if (str_contains($vehicle, 'BUS')) {
            return $distanceMeters > 10000 ? 2.50 : 1.00;
        }

        $distanceKm = max(0, $distanceMeters) / 1000;

        if (str_contains($line, 'KTM') || str_contains($vehicle, 'COMMUTER_TRAIN')) {
            return match (true) {
                $distanceKm <= 5 => 1.60,
                $distanceKm <= 15 => 2.70,
                $distanceKm <= 30 => 4.30,
                default => 6.00,
            };
        }

        if (str_contains($line, 'MRT')
            || str_contains($line, 'LRT')
            || str_contains($line, 'MONORAIL')
            || in_array($vehicle, ['SUBWAY', 'TRAM'], true)) {
            return match (true) {
                $distanceKm <= 4 => 1.30,
                $distanceKm <= 9 => 2.10,
                $distanceKm <= 15 => 3.20,
                default => 4.50,
            };
        }

        return null;
    }
}
