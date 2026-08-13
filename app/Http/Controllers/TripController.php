<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index()
    {
        $trips = Trip::where('user_id', auth()->id())->get();

        return view('trips.index', compact('trips'));
    }

    public function create()
    {
        return view('itineraries.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'co2_kg'     => 'required|integer|min:0',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['days'] = \Carbon\Carbon::parse($validated['start_date'])
            ->diffInDays(\Carbon\Carbon::parse($validated['end_date'])) + 1;

        $trip = Trip::create($validated);

        return redirect()
            ->route('itineraries.show', $trip)
            ->with('status', 'Itinerary created.');
    }

    public function show(Trip $trip)
    {
        abort_unless($trip->user_id === auth()->id(), 403);

        return view('itineraries.show', compact('trip'));
    }
}