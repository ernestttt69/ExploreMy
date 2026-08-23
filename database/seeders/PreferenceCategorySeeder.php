<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PreferenceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('seeders/data/preference_categories.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::table('preference_categories')->upsert($rows, ['preference_id'], ['category_name']);
    }
}
