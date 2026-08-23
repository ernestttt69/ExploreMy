<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;

class SavedPlaceController extends Controller
{
    public function index()
    {
        $savedPlaces = Wishlist::with([
            'attraction.images',
            'attraction.state',
            'attraction.preferences',
        ])
            ->where('user_id', Auth::id())
            ->get();

        return view(
            'saved-places.index',
            compact('savedPlaces')
        );
    }
}