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
		$user = Auth::user();
		$fields = ['name', 'email', 'profile_picture', 'phone', 'date_of_birth'];
		$completed = collect($fields)->filter(fn ($field) => filled($user->{$field}))->count();
		$completion = (int) round(($completed / count($fields)) * 100);
		$activities = LoginActivity::where('user_id', $user->user_id)->latest('logged_in_at')->limit(5)->get();
		$categories = PreferenceCategory::orderBy('preference_id')->get();
		$selectedPreferences = $user->preferenceCategories()->pluck('preference_categories.preference_id')->all();

		return view('profile', compact('user', 'completion', 'activities', 'categories', 'selectedPreferences'));
	}

	public function update(Request $request)
	{
		/** @var User $user */
		$user = Auth::user();
		$previousLanguage = $user->preferred_language;

		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'profile_picture' => 'nullable|image|max:2048',
			'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
			'date_of_birth' => ['nullable', 'date', 'before:today'],
			'preferences' => ['nullable', 'array'],
			'preferences.*' => ['integer', 'distinct', 'exists:preference_categories,preference_id'],
			'preferred_language' => ['required', 'in:en,ms,zh'],
			'personalisation_consent' => ['nullable', 'boolean'],
		]);


		$user->name = $request->name;
		$user->phone = $request->phone;
		$user->date_of_birth = $request->date_of_birth;
		$user->preferred_language = $request->preferred_language;
		$user->personalisation_consent = $request->boolean('personalisation_consent');
		$oldProfilePicture = null;


		if($request->hasFile('profile_picture'))
		{
			$image = $request->file('profile_picture');
			$profileImageDirectory = public_path('profile_images');

			File::ensureDirectoryExists($profileImageDirectory, 0775, true);
			if (! is_writable($profileImageDirectory)) {
				@chmod($profileImageDirectory, 0775);
			}

			if (! is_writable($profileImageDirectory)) {
				return back()
					->withInput()
					->withErrors(['profile_picture' => 'The profile image directory is not writable. Please check its folder permissions.']);
			}

			$imageName = Str::uuid().'.'.$image->extension();
			$oldProfilePicture = $user->profile_picture;

			$image->move(
				$profileImageDirectory,
				$imageName
			);

			$user->profile_picture = '/profile_images/'.$imageName;
		}


		$user->save();
		$user->preferenceCategories()->sync($validated['preferences'] ?? []);

		// The middleware selected the locale before this request changed the
		// preference. Switch immediately so the redirected flash message is
		// translated using the language the user just selected.
		app()->setLocale($user->preferred_language);

		if ($oldProfilePicture && str_starts_with($oldProfilePicture, '/profile_images/')) {
			File::delete(public_path(ltrim($oldProfilePicture, '/')));
		}

		if ($request->expectsJson()) {
			return response()->json([
				'message' => __('ui.messages.profile_updated'),
				'name' => $user->name,
				'profilePicture' => $user->profile_picture,
				'redirect' => $previousLanguage !== $user->preferred_language
					? route('profile')
					: null,
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
