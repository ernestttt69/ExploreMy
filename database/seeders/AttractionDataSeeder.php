<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttractionDataSeeder extends Seeder
{
    public function run(): void
    {
        $attractionCsv = base_path('scraper/output/attractions.csv');
        $preferenceCsv = base_path('scraper/output/attraction_preferences.csv');
        $imageCsv = base_path('scraper/output/downloaded_images.csv');

        if (!file_exists($attractionCsv)) {
            throw new \Exception(
                "attractions.csv not found: " . $attractionCsv
            );
        }

        if (!file_exists($preferenceCsv)) {
            throw new \Exception(
                "attraction_preferences.csv not found: " . $preferenceCsv
            );
        }

        if (!file_exists($imageCsv)) {
            throw new \Exception(
                "downloaded_images.csv not found: " . $imageCsv
            );
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('attraction_image')->truncate();
        DB::table('attraction_preferences')->truncate();
        DB::table('attractions')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $attractions = [];

        $file = fopen($attractionCsv, 'r');

        $headers = fgetcsv($file);

        $headers = array_map(function ($header) {
            return trim(
                $header,
                "\xEF\xBB\xBF \t\n\r\0\x0B"
            );
        }, $headers);

        while (($row = fgetcsv($file)) !== false) {

            if (count($headers) !== count($row)) {
                continue;
            }

            $data = array_combine($headers, $row);

            $placeId = trim(
                $data['place_id'] ?? ''
            );

            if ($placeId === '') {
                continue;
            }

            $stateId = trim(
                $data['state_id'] ?? ''
            );

            $attractionName = trim(
                $data['attraction_name'] ?? ''
            );

            if ($attractionName === '') {
                continue;
            }

            $attractions[] = [
                'place_id' => $placeId,

                'state_id' => $stateId !== ''
                    ? (int) $stateId
                    : null,

                'attraction_name' => $attractionName,

                'description' => trim(
                    $data['description'] ?? ''
                ),

                'location' => trim(
                    $data['location'] ?? ''
                ),

                'operating_hours' => trim(
                    $data['operating_hours'] ?? ''
                ),

                'entrance_fee' => trim(
                    $data['entrance_fee'] ?? ''
                ) !== ''
                    ? trim($data['entrance_fee'])
                    : 'Price unavailable',

                'budget_level' => trim(
                    $data['budget_level'] ?? ''
                ) !== ''
                    ? trim($data['budget_level'])
                    : 'Price unavailable',

                'nearby_transport' => trim(
                    $data['nearby_transport'] ?? ''
                ),

                'rating' => trim(
                    $data['rating'] ?? ''
                ) !== ''
                    ? (float) $data['rating']
                    : null,
            ];
        }

        fclose($file);

        $attractions = collect($attractions)
            ->unique('place_id')
            ->values()
            ->all();

        foreach (array_chunk($attractions, 500) as $chunk) {
            DB::table('attractions')->insert($chunk);
        }

        $placeIdMap = [];

        $databaseAttractions = DB::table('attractions')
            ->select(
                'attraction_id',
                'place_id'
            )
            ->get();

        foreach ($databaseAttractions as $attraction) {
            $placeIdMap[
                $attraction->place_id
            ] = $attraction->attraction_id;
        }

        $preferences = [];

        $file = fopen($preferenceCsv, 'r');

        $headers = fgetcsv($file);

        $headers = array_map(function ($header) {
            return trim(
                $header,
                "\xEF\xBB\xBF \t\n\r\0\x0B"
            );
        }, $headers);

        while (($row = fgetcsv($file)) !== false) {

            if (count($headers) !== count($row)) {
                continue;
            }

            $data = array_combine($headers, $row);

            $placeId = trim(
                $data['place_id'] ?? ''
            );

            $preferenceId = (int) (
                $data['preference_id'] ?? 0
            );

            if (
                $placeId === '' ||
                $preferenceId <= 0 ||
                !isset($placeIdMap[$placeId])
            ) {
                continue;
            }

            $preferences[] = [
                'attraction_id' => $placeIdMap[$placeId],
                'preference_id' => $preferenceId,
            ];
        }

        fclose($file);

        $preferences = collect($preferences)
            ->unique(function ($item) {
                return
                    $item['attraction_id']
                    . '-'
                    . $item['preference_id'];
            })
            ->values()
            ->all();

        foreach (array_chunk($preferences, 500) as $chunk) {
            DB::table('attraction_preferences')
                ->insert($chunk);
        }

        $images = [];

        $file = fopen($imageCsv, 'r');

        $headers = fgetcsv($file);

        $headers = array_map(function ($header) {
            return trim(
                $header,
                "\xEF\xBB\xBF \t\n\r\0\x0B"
            );
        }, $headers);

        while (($row = fgetcsv($file)) !== false) {

            if (count($headers) !== count($row)) {
                continue;
            }

            $data = array_combine($headers, $row);

            $placeId = trim(
                $data['place_id'] ?? ''
            );

            $imagePath = trim(
                $data['image_path'] ?? ''
            );

            if (
                $placeId === '' ||
                $imagePath === '' ||
                !isset($placeIdMap[$placeId])
            ) {
                continue;
            }

            $images[] = [
                'attraction_id' => $placeIdMap[$placeId],
                'image_path' => $imagePath,
            ];
        }

        fclose($file);

        $images = collect($images)
            ->unique(function ($item) {
                return
                    $item['attraction_id']
                    . '-'
                    . $item['image_path'];
            })
            ->values()
            ->all();

        foreach (array_chunk($images, 500) as $chunk) {
            DB::table('attraction_image')
                ->insert($chunk);
        }

        $this->command->info(
            'Attractions imported: '
            . count($attractions)
        );

        $this->command->info(
            'Category relationships imported: '
            . count($preferences)
        );

        $this->command->info(
            'Image relationships imported: '
            . count($images)
        );
    }
}