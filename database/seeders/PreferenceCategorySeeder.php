<?php

namespace Database\Seeders;

use App\Models\PreferenceCategory;
use Illuminate\Database\Seeder;

class PreferenceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Adventure',
            'Beach',
            'Culture',
            'Food',
            'History',
            'Nature',
            'Shopping',
        ];

        foreach ($categories as $category) {
            PreferenceCategory::firstOrCreate([
                'category_name' => $category,
            ]);
        }
    }
}
