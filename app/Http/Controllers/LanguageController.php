<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function update(Request $request)
    {
        if ($request->user()) return redirect()->route('profile');
        $validated = $request->validate(['language' => ['required', 'in:en,ms,zh']]);
        $locale = $validated['language'];
        $request->session()->put('locale', $locale);

        return back();
    }
}
