<?php

namespace App\Services;

class EcoAlternativeService
{
    private const EMISSION_FACTORS = [
        'flight' => 0.255,
        'private_car' => 0.192,
        'taxi' => 0.192,
        'ferry' => 0.115,
        'bus' => 0.105,
        'train' => 0.041,
        'electric_train' => 0.035,
        'walking' => 0.0,
        'cycling' => 0.0,
    ];

    private const LABELS = [
        'flight' => 'Flight',
        'private_car' => 'Private car',
        'taxi' => 'Taxi',
        'ferry' => 'Ferry',
        'bus' => 'Bus',
        'train' => 'Train',
        'electric_train' => 'Electric train',
        'walking' => 'Walking',
        'cycling' => 'Cycling',
    ];

    /**
     * Estimate a transport item's carbon footprint in kilograms of CO2e.
     */
    public function estimate(?string $mode, ?float $distanceKm): float
    {
        $factor = self::EMISSION_FACTORS[$mode] ?? 0.0;

        return round(max(0, (float) $distanceKm) * $factor, 2);
    }

    /**
     * Return a lower-carbon alternative when the selected mode is high emission.
     *
     * @return array<string, mixed>|null
     */
    public function suggestionFor(?string $mode, ?float $distanceKm): ?array
    {
        $alternatives = [
            'flight' => ['mode' => 'electric_train', 'reason' => 'Rail is a lower-carbon option for many domestic routes.'],
            'private_car' => ['mode' => 'train', 'reason' => 'Public rail reduces emissions per traveller.'],
            'taxi' => ['mode' => 'bus', 'reason' => 'A shared bus trip has a smaller footprint per traveller.'],
        ];

        if (! isset($alternatives[$mode])) {
            return null;
        }

        $alternative = $alternatives[$mode];
        $current = $this->estimate($mode, $distanceKm);
        $replacement = $this->estimate($alternative['mode'], $distanceKm);

        return [
            'mode' => $alternative['mode'],
            'label' => self::LABELS[$alternative['mode']],
            'reason' => $alternative['reason'],
            'current_carbon_kg' => $current,
            'alternative_carbon_kg' => $replacement,
            'saving_kg' => round(max(0, $current - $replacement), 2),
        ];
    }

    /**
     * Get a display label for a stored transport mode.
     */
    public function labelFor(?string $mode): string
    {
        return self::LABELS[$mode] ?? 'Not specified';
    }
}
