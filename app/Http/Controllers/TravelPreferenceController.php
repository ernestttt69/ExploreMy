<?php

namespace App\Http\Controllers;

use App\Models\PreferenceCategory;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TravelPreferenceController extends Controller
{
    public function edit()
    {
        $categories = PreferenceCategory::all();

        $selectedPreferences = UserPreference::where('user_id', Auth::id())
            ->pluck('preference_id')
            ->toArray();

        return view('travel-preferences.edit', compact(
            'categories',
            'selectedPreferences'
        ));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['integer', 'distinct', 'exists:preference_categories,preference_id'],
        ]);

        DB::transaction(function () use ($validated) {
            UserPreference::where('user_id', Auth::id())->delete();

            foreach ($validated['preferences'] ?? [] as $preferenceId) {
                UserPreference::create([
                    'user_id' => Auth::id(),
                    'preference_id' => $preferenceId,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Travel preferences updated.');
    }
}
