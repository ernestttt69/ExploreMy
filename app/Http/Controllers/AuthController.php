<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // This is the missing method Laravel is looking for!
    public function login()
    {
        return view('login');
    }

    // Google login placeholder
    public function googleLogin(Request $request)
    {
        // Google authentication logic here
    }

    // Logout method
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}