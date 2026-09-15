<?php
namespace App\Http\Controllers;
use App\Models\PreferenceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetupController extends Controller
{
    public function show(Request $request)
    {
        if (!$request->user()->setup_required) return redirect()->route('profile');
        return view('auth.setup', ['categories' => PreferenceCategory::orderBy('preference_id')->get()]);
    }
    public function store(Request $request)
    {
        if (!$request->user()->setup_required) return redirect()->route('profile');
        $data = $request->validate([
            'preferred_language' => ['required', 'in:en,ms,zh'],
            'preferences' => ['nullable', 'array'],
            'preferences.*' => ['integer', 'distinct', 'exists:preference_categories,preference_id'],
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = $request->user();
            $user->preferred_language = $data['preferred_language'];
            $user->setup_required = false;
            $user->save();
            $user->preferenceCategories()->sync($data['preferences'] ?? []);
        });
        $request->session()->put('locale', $data['preferred_language']);
        return redirect()->route('dashboard')->with('setup_complete', true);
    }
}
