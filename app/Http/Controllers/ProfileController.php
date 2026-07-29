<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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


		if($request->hasFile('profile_picture'))
		{
			$image = $request->file('profile_picture');

			$imageName = time().'.'.$image->getClientOriginalExtension();

			$image->move(
				public_path('profile_images'),
				$imageName
			);

			$user->profile_picture = '/profile_images/'.$imageName;
		}


		$user->save();


		return back()->with(
			'success',
			'Profile updated successfully!'
		);
	}
}