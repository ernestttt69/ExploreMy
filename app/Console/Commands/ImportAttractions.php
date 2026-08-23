<?php

namespace App\Console\Commands;

use App\Models\Attraction;
use App\Models\AttractionImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportAttractions extends Command
{
    protected $signature = 'attractions:import';

    protected $description = 'Import attractions and downloaded images from CSV files';

    public function handle()
    {
        $basePath = base_path('scraper/output');

        $attractionsFile = $basePath . '/attractions.csv';
        $imagesFile = $basePath . '/downloaded_images.csv';

        if (!File::exists($attractionsFile)) {
            $this->error('attractions.csv not found.');
            return Command::FAILURE;
        }

        if (!File::exists($imagesFile)) {
            $this->error('downloaded_images.csv not found.');
            return Command::FAILURE;
        }

        $this->info('Importing attractions...');

        $attractionHandle = fopen($attractionsFile, 'r');

        $headers = fgetcsv($attractionHandle);

        $headers = array_map(function ($header) {
            return trim($header, "\xEF\xBB\xBF \t\n\r\0\x0B");
        }, $headers);

        $createdAttractions = 0;
        $updatedAttractions = 0;

        while (($row = fgetcsv($attractionHandle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $row);

            if (empty($data['place_id'])) {
                continue;
            }

            if (empty($data['state_id'])) {
                continue;
            }

            $attraction = Attraction::updateOrCreate(
                [
                    'place_id' => $data['place_id'],
                ],
                [
                    'state_id' => $data['state_id'],
                    'attraction_name' => $data['attraction_name'],
                    'category' => $data['category'],
                    'description' => $data['description'] ?: null,
                    'location' => $data['location'],
                    'operating_hours' => $data['operating_hours'] ?: null,
                    'budget_level' => $data['budget_level'] ?: null,
                    'nearby_transport' => $data['nearby_transport'] ?: null,
                    'rating' => $data['rating'] ?: null,
                ]
            );

            if ($attraction->wasRecentlyCreated) {
                $createdAttractions++;
            } else {
                $updatedAttractions++;
            }
        }

        fclose($attractionHandle);

        $this->info('Attractions imported.');
        $this->info("New attractions: {$createdAttractions}");
        $this->info("Updated attractions: {$updatedAttractions}");

        $this->info('');
        $this->info('Importing images...');

        $imageHandle = fopen($imagesFile, 'r');

        $imageHeaders = fgetcsv($imageHandle);

        $imageHeaders = array_map(function ($header) {
            return trim($header, "\xEF\xBB\xBF \t\n\r\0\x0B");
        }, $imageHeaders);

        $createdImages = 0;
        $skippedImages = 0;

        while (($row = fgetcsv($imageHandle)) !== false) {
            if (count($row) !== count($imageHeaders)) {
                continue;
            }

            $data = array_combine($imageHeaders, $row);

            if (empty($data['attraction_name'])) {
                continue;
            }

            if (empty($data['image_path'])) {
                continue;
            }

            $attraction = Attraction::where(
                'attraction_name',
                $data['attraction_name']
            )->first();

            if (!$attraction) {
                $this->warn(
                    "Attraction not found: {$data['attraction_name']}"
                );

                continue;
            }

            $existingImage = AttractionImage::where(
                'attraction_id',
                $attraction->attraction_id
            )->where(
                'image_path',
                $data['image_path']
            )->first();

            if ($existingImage) {
                $skippedImages++;
                continue;
            }

            AttractionImage::create([
                'attraction_id' => $attraction->attraction_id,
                'image_path' => $data['image_path'],
            ]);

            $createdImages++;
        }

        fclose($imageHandle);

        $this->info('Images imported.');
        $this->info("New images: {$createdImages}");
        $this->info("Skipped existing images: {$skippedImages}");

        $this->info('');
        $this->info('Import completed successfully.');

        return Command::SUCCESS;
    }
}