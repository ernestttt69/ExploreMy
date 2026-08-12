<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\State;
use App\Models\Attraction;

class TourismDataSeeder extends Seeder
{
    public function run()
    {
        // 1. Seed States from database/data/states.csv
        $statesPath = database_path('data/states.csv');

        if (!file_exists($statesPath)) {
            $this->command->error("File NOT found: {$statesPath}");
        } else {
            $handle = fopen($statesPath, 'r');
            fgetcsv($handle); // Skip header row

            $stateCount = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if (!empty($row[0])) {
                    State::updateOrCreate(
                        ['state_id' => $row[0]],
                        [
                            'state_name'        => $row[1] ?? '',
                            'state_description' => $row[2] ?? null,
                        ]
                    );
                    $stateCount++;
                }
            }
            fclose($handle);
            $this->command->info("Seeded {$stateCount} states successfully.");
        }

        // 2. Seed Attractions from database/data/attractions.csv
        $attractionsPath = database_path('data/attractions.csv');

        if (!file_exists($attractionsPath)) {
            $this->command->error("File NOT found: {$attractionsPath}");
        } else {
            $handle = fopen($attractionsPath, 'r');
            fgetcsv($handle); // Skip header row

            $attractionCount = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if (!empty($row[0])) {
                    Attraction::updateOrCreate(
                        ['attraction_id' => $row[0]],
                        [
                            'state_id'         => $row[1] ?? 1,
                            'attraction_name'  => $row[2] ?? '',
                            'category'         => $row[3] ?? '',
                            'description'      => $row[4] ?? '',
                            'location'         => $row[5] ?? '',
                            'operating_hours'  => $row[6] ?? null,
                            'entrance_fee'     => is_numeric($row[7] ?? null) ? $row[7] : 0.00,
                            'budget_level'     => $row[8] ?? 'Medium',
                            'nearby_transport' => $row[9] ?? 'N/A',
                            'rating'           => is_numeric($row[10] ?? null) ? $row[10] : 0.0,
                        ]
                    );
                    $attractionCount++;
                }
            }
            fclose($handle);
            $this->command->info("Seeded {$attractionCount} attractions successfully.");
        }
    }
}