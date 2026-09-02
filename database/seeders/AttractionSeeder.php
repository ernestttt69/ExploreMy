<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttractionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('seeders/data/attractions.json')), true, 512, JSON_THROW_ON_ERROR);
        $updateColumns = array_values(array_diff(array_keys($rows[0] ?? []), ['attraction_id']));

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('attractions')->upsert($chunk, ['attraction_id'], $updateColumns);
        }
    }
}
