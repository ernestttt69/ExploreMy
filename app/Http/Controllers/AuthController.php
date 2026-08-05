<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Google\Client;

class AuthController extends Controller
{
	public function login()
	{
		return view('auth.login');
	}

	public function googleLogin(Request $request)
	{
		try {
			$client = new Client([
				'client_id' => env('GOOGLE_CLIENT_ID')
			]);

			$payload = $client->verifyIdToken($request->credential);

			if (!$payload) {
				return response()->json([
					'success' => false,
					'message' => 'Invalid Google Token'
				]);
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

            return response()->json([
                'success' => true,
                'redirect' => '/dashboard'
            ]);

		} catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
        }
	}
}