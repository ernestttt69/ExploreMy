<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AttractionImageSeeder extends Seeder
{
    public function run(): void
    {
        // Path pointing directly to database/data/attraction_image.csv
        $csvFile = database_path('data/attraction_image.csv');

        if (!File::exists($csvFile)) {
            $this->command->error("CSV file not found at: {$csvFile}");
            return;
        }

        // Get existing attraction IDs to avoid foreign key errors
        $existingAttractionIds = DB::table('attractions')->pluck('attraction_id')->toArray();

        if (empty($existingAttractionIds)) {
            $this->command->warn('No attractions found in the database. Seed attractions first!');
            return;
        }

        $file = fopen($csvFile, 'r');
        $header = fgetcsv($file); // Read and skip header row

        $imagesToInsert = [];

        while (($row = fgetcsv($file)) !== false) {
            if (empty($row) || count($row) < 2) {
                continue;
            }

            // Assuming Column 0 = attraction_id, Column 1 = image_path
            $attractionId = trim($row[0]);
            $imagePath = trim($row[1]);

            if (in_array($attractionId, $existingAttractionIds) && !empty($imagePath)) {
                $imagesToInsert[] = [
                    'attraction_id' => $attractionId,
                    'image_path'   => $imagePath,
                ];
            }
        }

        fclose($file);

        if (!empty($imagesToInsert)) {
            // Clear existing records in attraction_image first to avoid duplicate primary keys
            DB::table('attraction_image')->truncate();

            foreach (array_chunk($imagesToInsert, 100) as $chunk) {
                DB::table('attraction_image')->insert($chunk);
            }
            $this->command->info('Successfully seeded ' . count($imagesToInsert) . ' images into attraction_image!');
        } else {
            $this->command->warn('No valid rows found in CSV to insert.');
        }
    }
}