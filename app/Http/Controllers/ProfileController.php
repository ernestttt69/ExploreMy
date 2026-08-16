<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
	public function update(Request $request)
	{
		$user = Auth::user();

		$request->validate([
			'name' => 'required|string|max:255',
			'profile_picture' => 'nullable|image|max:2048'
		]);


		$user->name = $request->name;
		$oldProfilePicture = null;


		if($request->hasFile('profile_picture'))
		{
			$image = $request->file('profile_picture');

			$imageName = Str::uuid().'.'.$image->extension();
			$oldProfilePicture = $user->profile_picture;

			$image->move(
				public_path('profile_images'),
				$imageName
			);

			$user->profile_picture = '/profile_images/'.$imageName;
		}


		$user->save();

		if ($oldProfilePicture && str_starts_with($oldProfilePicture, '/profile_images/')) {
			File::delete(public_path(ltrim($oldProfilePicture, '/')));
		}


		return back()->with(
			'success',
			'Profile updated successfully!'
		);
	}
}
