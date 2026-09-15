<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Models\LoginActivity;
use App\Models\PreferenceCategory;
use App\Models\User;

class ProfileController extends Controller
{
	public function show()
	{
		/** @var User $user */
		$user = Auth::user();
		$activities = LoginActivity::where('user_id', $user->user_id)->latest('logged_in_at')->limit(5)->get();
		$categories = PreferenceCategory::orderBy('preference_id')->get();
		$selectedPreferences = $user->preferenceCategories()->pluck('preference_categories.preference_id')->all();

		return view('profile', compact('user', 'activities', 'categories', 'selectedPreferences'));
	}

	public function update(Request $request)
	{
		/** @var User $user */
		$user = Auth::user();
		$previousLanguage = $user->preferred_language;

		$validated = $request->validate([
            'preferred_language' => ['sometimes', 'required', 'in:en,ms,zh'],
			'profile_picture' => 'nullable|image|max:2048',
			'preferences' => ['nullable', 'array'],
			'preferences.*' => ['integer', 'distinct', 'exists:preference_categories,preference_id'],
		], [
            'profile_picture.*' => __('profile_errors.photo'),
            'preferences.*' => __('profile_errors.preferences'),
            'preferences.*.*' => __('profile_errors.preferences'),
        ]);


		$oldProfilePicture = null;


		if($request->hasFile('profile_picture'))
		{
			$image = $request->file('profile_picture');
			$profileImageDirectory = public_path('profile_images');

            try {
			File::ensureDirectoryExists($profileImageDirectory, 0775, true);
			if (! is_writable($profileImageDirectory)) {
				@chmod($profileImageDirectory, 0775);
			}

			if (! is_writable($profileImageDirectory)) {
                throw new \RuntimeException('Profile image directory is not writable.');
			}

			$imageName = Str::uuid().'.'.$image->extension();
			$oldProfilePicture = $user->profile_picture;

			$image->move(
				$profileImageDirectory,
				$imageName
			);

			$user->profile_picture = '/profile_images/'.$imageName;
            } catch (\Throwable $exception) {
                report($exception);
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'profile_picture' => __('profile_errors.upload'),
                ]);
            }
		}


		$user->preferred_language = $validated['preferred_language'] ?? $user->preferred_language;
		$user->save();
		$user->preferenceCategories()->sync($validated['preferences'] ?? []);
        app()->setLocale($user->preferred_language);
        $request->session()->put('locale', $user->preferred_language);

		if ($oldProfilePicture && str_starts_with($oldProfilePicture, '/profile_images/')) {
			File::delete(public_path(ltrim($oldProfilePicture, '/')));
		}

		if ($request->expectsJson()) {
			return response()->json([
				'message' => __('ui.messages.profile_updated'),
				'name' => $user->name,
				'profilePicture' => $user->profile_picture,
				'redirect' => $previousLanguage !== $user->preferred_language ? route('profile') : null,
			]);
		}

		return back()->with(
			'success',
			__('ui.messages.profile_updated')
		);
	}

	public function destroy(Request $request)
	{
		$request->validate(['confirmation' => ['required', 'in:DELETE']]);
		/** @var User $user */
		$user = Auth::user();
		$picture = $user->profile_picture;
		Auth::logout();
		$user->delete();
		$request->session()->invalidate();
		$request->session()->regenerateToken();
		if ($picture && str_starts_with($picture, '/profile_images/')) {
			File::delete(public_path(ltrim($picture, '/')));
		}
		return redirect('/login')->with('success', __('ui.messages.account_deleted'));
	}
}
