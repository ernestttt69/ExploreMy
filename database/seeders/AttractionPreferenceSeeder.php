<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttractionPreferenceSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('seeders/data/attraction_preferences.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('attraction_preferences')->upsert($chunk, ['attraction_preference_id'], ['attraction_id', 'preference_id']);
        }
    }
}
