<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RoutePlanningController extends Controller
{
    public function index()
    {
        return view('route-planning.route');
    }

    public function storePreference(Request $request)
    {
        $validated = $request->validate([
            'optimization_preference' => [
                'required',
                'in:fastest,shortest,eco,lowest_cost',
            ],
        ]);

        return back()->with(
            'success',
            'Preference selected: ' .
            $validated['optimization_preference']
        );
    }
}