<?php

namespace App\Http\Controllers;

use App\Models\Attraction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SavedPlaceController extends Controller
{
    public function index()
    {
        $savedPlaces = Auth::user()->savedAttractions()
            ->with('state')
            ->orderByPivot('created_at', 'desc')
            ->get();

        return view('saved-places.index', compact('savedPlaces'));
    }

    public function store(Attraction $attraction): RedirectResponse
    {
        Auth::user()->savedAttractions()->syncWithoutDetaching([$attraction->attraction_id]);

        return back()->with('success', 'Place saved successfully.');
    }

    public function destroy(Attraction $attraction): RedirectResponse
    {
        Auth::user()->savedAttractions()->detach($attraction->attraction_id);

        return back()->with('success', 'Place removed from your saved collection.');
    }
}
