<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        $states = [1 => 'Johor', 2 => 'Kedah', 3 => 'Kelantan', 4 => 'Melaka', 5 => 'Negeri Sembilan', 6 => 'Pahang', 7 => 'Penang', 8 => 'Perak', 9 => 'Perlis', 10 => 'Sabah', 11 => 'Sarawak', 12 => 'Selangor', 13 => 'Terengganu', 14 => 'Kuala Lumpur', 15 => 'Labuan', 16 => 'Putrajaya'];
        foreach ($states as $id => $name) {
            State::updateOrCreate(['state_id' => $id], ['state_name' => $name]);
        }
    }
}
