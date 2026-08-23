<?php

namespace App\Http\Controllers;

use App\Models\Attraction;
use App\Models\Wishlist;
use Illuminate\Http\RedirectResponse;
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

    public function store(Attraction $attraction): RedirectResponse
    {
        Wishlist::firstOrCreate([
            'user_id' => Auth::id(),
            'attraction_id' => $attraction->attraction_id,
        ]);

        return back()->with('success', 'Place saved successfully.');
    }

    public function destroy(Attraction $attraction): RedirectResponse
    {
        Wishlist::where('user_id', Auth::id())
            ->where('attraction_id', $attraction->attraction_id)
            ->delete();

        return back()->with('success', 'Place removed from your saved collection.');
    }
}
