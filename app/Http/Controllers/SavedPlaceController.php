<?php

namespace App\Http\Controllers;

class SavedPlaceController extends Controller
{
    /**
     * Display the authenticated user's saved places.
     */
    public function index()
    {
        // Temporary data until the add/save place feature and database are ready.
        $savedPlaces = [
            [
                'name' => 'Petronas Twin Towers',
                'location' => 'Kuala Lumpur',
                'category' => 'Landmark',
                'description' => 'Visit Malaysia’s iconic twin towers and enjoy the city view from the Skybridge.',
                'image' => 'https://images.unsplash.com/photo-1596422846543-75c6fc197f07?auto=format&fit=crop&w=900&q=80',
            ],
            [
                'name' => 'Batu Caves',
                'location' => 'Selangor',
                'category' => 'Culture',
                'description' => 'Explore the colourful temple complex and its famous limestone caves.',
                'image' => 'https://images.unsplash.com/photo-1740360656004-30cb44b41850?auto=format&fit=crop&w=900&q=80',
            ],
            [
                'name' => 'George Town',
                'location' => 'Penang',
                'category' => 'Heritage',
                'description' => 'Discover heritage buildings, street art, and some of Malaysia’s best local food.',
                'image' => 'https://images.unsplash.com/photo-1583265266785-aab9e443ee68?auto=format&fit=crop&w=900&q=80',
            ],
        ];

        return view('saved-places.index', compact('savedPlaces'));
    }
}
