<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return redirect()
                ->back()
                ->with('error', 'User not found.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'profile_picture' => 'nullable|image|max:2048',
        ]);

        $user->name = $request->name;

        $oldProfilePicture = $user->profile_picture;

        if ($request->hasFile('profile_picture')) {
            $image = $request->file('profile_picture');

            $imageName = Str::uuid() . '.' . $image->extension();

            $profileImageDirectory = public_path('profile_images');

            if (!File::exists($profileImageDirectory)) {
                File::makeDirectory(
                    $profileImageDirectory,
                    0755,
                    true
                );
            }

            $image->move(
                $profileImageDirectory,
                $imageName
            );

            $user->profile_picture = '/profile_images/' . $imageName;
        }

        $user->save();

        if (
            $oldProfilePicture &&
            str_starts_with(
                $oldProfilePicture,
                '/profile_images/'
            )
        ) {
            $oldFile = public_path(
                ltrim($oldProfilePicture, '/')
            );

            if (File::exists($oldFile)) {
                File::delete($oldFile);
            }
        }

        return redirect()
            ->back()
            ->with(
                'success',
                'Profile updated successfully!'
            );
    }
}