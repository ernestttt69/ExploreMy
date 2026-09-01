<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Google\Client;
use App\Models\LoginActivity;
use Illuminate\Support\Carbon;
use App\Services\GreenRewardService;

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

			$user = User::firstOrNew(['google_id' => $payload['sub']]);
			if (! $user->exists) {
				$user->name = $payload['name'];
				$user->profile_picture = $payload['picture'];
			}
			$user->email = $payload['email'];
			$user->save();

            Auth::login($user);
            $request->session()->regenerate();

            $user->forceFill(['last_login_at' => Carbon::now()])->save();
            LoginActivity::create([
                'user_id' => $user->user_id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'logged_in_at' => Carbon::now(),
            ]);
			app(GreenRewardService::class)->queueActivity($user, 'daily_login');

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
