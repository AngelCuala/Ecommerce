<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The signed-in user's profile photo, for every role. The account pages send it as soon as a photo is
 * chosen (public/js/photo-upload.js), so it no longer waits for the page's Save button.
 */
class ProfilePhotoController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'avatar.required' => 'Choose a photo first.',
            'avatar.image'    => 'The profile photo must be a JPG, PNG or WebP image.',
            'avatar.mimes'    => 'The profile photo must be a JPG, PNG or WebP image.',
            'avatar.max'      => 'The profile photo can be at most 5 MB.',
        ]);

        $user = $request->user();
        $old = $user->profile_photo_path;
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->forceFill(['profile_photo_path' => $path])->save();
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return response()->json(['message' => 'Profile photo updated.', 'url' => asset('storage/' . $path)]);
    }
}
