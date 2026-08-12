<?php

namespace App\Http\Controllers;

use App\Models\State;
use App\Models\Attraction;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * View Recommended Attractions & Filter Attractions
     */
    public function index(Request $request)
{
    $states = State::all();
    $attractions = collect();

    if ($request->has('search')) {
        $request->validate([
            'state_id' => 'required|exists:states,state_id',
            'trip_duration' => 'required|numeric|min:1',
        ]);

        $query = Attraction::query()->where('state_id', $request->state_id);

        // Filter by Category
        if ($request->filled('category')) {
            if ($request->category === 'Rating') {
                $query->orderBy('rating', 'desc');
            } else {
                $query->where('category', $request->category);
            }
        }

        // Single Selection Budget Filter
        if ($request->filled('budget')) {
            switch ($request->budget) {
                case 'free':
                    $query->where('entrance_fee', 0);
                    break;
                case 'under_50':
                    $query->whereBetween('entrance_fee', [1, 49]);
                    break;
                case '50_150':
                    $query->whereBetween('entrance_fee', [50, 150]);
                    break;
                case 'above_150':
                    $query->where('entrance_fee', '>', 150);
                    break;
            }
        }

        // Single Selection Rating Filter
        if ($request->filled('rating')) {
            $query->where('rating', '>=', (float) $request->rating);
        }

        $attractions = $query->get();
    }

    return view('recommendations.index', compact('states', 'attractions'));
}

    /**
     * View Attraction Details
     */
    public function show($id)
    {
        try {
            // Alternative Flow A1: Handle non-existent attraction
            $attraction = Attraction::with(['state', 'images'])->findOrFail($id);
            return view('recommendations.show', compact('attraction'));
        } catch (\Exception $e) {
            return redirect()->route('recommendations.index')
                ->with('error', 'Unable to retrieve attraction details. Please try again.');
        }
    }
}