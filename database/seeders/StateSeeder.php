<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('seeders/data/states.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::table('states')->upsert($rows, ['state_id'], ['state_name']);
    }
}
