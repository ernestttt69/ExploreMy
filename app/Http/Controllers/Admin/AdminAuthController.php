<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        if ($request->session()->get('admin_authenticated') === true) {
            return redirect()->route('admin.attractions.index');
        }

        return view('admin.auth.login');
    }

    public function authenticate(Request $request)
    {
        $validated = $request->validate(['access_code' => ['required', 'string', 'max:100']]);
        $configuredCode = (string) config('admin.access_code');

        if ($configuredCode === '') {
            return back()->withErrors(['access_code' => 'Admin access code has not been configured.']);
        }

        if (! hash_equals($configuredCode, $validated['access_code'])) {
            return back()->withErrors(['access_code' => 'The access code is incorrect.'])->onlyInput();
        }

        $request->session()->regenerate();
        $request->session()->put('admin_authenticated', true);

        return redirect()->route('admin.attractions.index');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
