<?php

namespace Database\Seeders;

use App\Models\Attraction;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Seeder;

class AttractionSeeder extends Seeder
{
    public function run(): void
    {
        $johor = State::updateOrCreate(['state_id' => 1], ['state_name' => 'Johor']);

        $rows = [
            [
                'place_id' => 'ChIJM-12PIwL2jERSzVEGdIIW4E',
                'attraction_name' => 'LEGOLAND Malaysia',
                'category' => 'Nature, Family',
                'description' => "Themed rooms in a colourful hotel with multiple eateries, kids' play areas & interactive features.",
                'location' => "7, Persiaran Medini Utara 3, 79100 Iskandar Puteri, Johor Darul Ta'zim",
                'operating_hours' => "Monday: 10:00 AM – 6:00 PM\nTuesday: 10:00 AM – 6:00 PM\nWednesday: Closed\nThursday: 10:00 AM – 6:00 PM\nFriday: 10:00 AM – 6:00 PM\nSaturday: 10:00 AM – 6:00 PM\nSunday: 10:00 AM – 6:00 PM",
                'entrance_fee' => 'Price unavailable', 'budget_level' => 'Price unavailable', 'nearby_transport' => null, 'rating' => 4.4,
            ],
            [
                'place_id' => 'ChIJaQHwMpxg2jERxv766lBVHf0',
                'attraction_name' => 'Kota Tinggi Firefly Park',
                'category' => 'Nature',
                'description' => 'This evening amusement features a tranquil riverboat ride to view thousands of luminous fireflies.',
                'location' => "Jalan Kota Tinggi, 81900 Kota Tinggi, Johor Darul Ta'zim",
                'operating_hours' => "Monday: 1:00 – 11:00 PM\nTuesday: 1:00 – 11:00 PM\nWednesday: 1:00 – 11:00 PM\nThursday: 1:00 – 11:00 PM\nFriday: 1:00 – 11:00 PM\nSaturday: 1:00 – 11:00 PM\nSunday: 1:00 – 11:00 PM",
                'entrance_fee' => 'Price unavailable', 'budget_level' => 'Price unavailable', 'nearby_transport' => null, 'rating' => 4.8,
            ],
            [
                'place_id' => 'ChIJoUtF3YsL2jERG_6MZMmKYl0',
                'attraction_name' => 'LEGOLAND Waterpark Malaysia',
                'category' => 'Nature, Adventure, Family, Food & Drinks',
                'description' => 'A range of water-based activities for kids of all ages, with on-site dining & extreme rides.',
                'location' => '7, Jln Legoland, Bandar, 79250 Iskandar Puteri, Johor',
                'operating_hours' => "Monday: 10:00 AM – 6:00 PM\nTuesday: Closed\nWednesday: 10:00 AM – 6:00 PM\nThursday: 10:00 AM – 6:00 PM\nFriday: 10:00 AM – 6:00 PM\nSaturday: 10:00 AM – 6:00 PM\nSunday: 10:00 AM – 6:00 PM",
                'entrance_fee' => 'Price unavailable', 'budget_level' => 'Price unavailable', 'nearby_transport' => null, 'rating' => 4.4,
            ],
        ];

        $ids = collect($rows)->map(function (array $row) use ($johor) {
            return Attraction::updateOrCreate(['place_id' => $row['place_id']], $row + ['state_id' => $johor->state_id])->attraction_id;
        });

        User::each(fn (User $user) => $user->savedAttractions()->syncWithoutDetaching($ids));
    }
}
