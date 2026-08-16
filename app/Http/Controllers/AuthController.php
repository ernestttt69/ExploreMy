<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Google\Client;

class AuthController extends Controller
{
	public function login()
	{
		return view('auth.login');
	}

	public function googleLogin(Request $request)
	{
		$request->validate([
			'credential' => ['required', 'string'],
		]);

		try {
			$client = new Client([
				'client_id' => config('services.google.client_id'),
			]);

			$payload = $client->verifyIdToken($request->credential);

			if (!$payload) {
				return response()->json([
					'success' => false,
					'message' => 'Invalid Google Token'
				], 401);
			}

			$user = User::updateOrCreate(
				[
					'google_id' => $payload['sub']
				],
				[
					'name' => $payload['name'],
					'email' => $payload['email'],
					'profile_picture' => $payload['picture']
				]
			);

            Auth::login($user);
            $request->session()->regenerate();

            return response()->json([
                'success' => true,
                'redirect' => '/dashboard'
            ]);

		} catch (\Throwable $e) {
            Log::error('Google login failed', ['exception' => $e]);

            return response()->json([
				'success' => false,
				'message' => 'Google login failed. Please try again.',
            ], 500);
        }
	}
}
