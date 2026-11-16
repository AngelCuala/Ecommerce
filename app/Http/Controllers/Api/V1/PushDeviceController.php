<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The app registers its phone for push notifications after signing in (and again when Firebase renews the token). */
class PushDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => 'required|string|max:512',
            'platform' => 'nullable|in:android,ios',
        ]);

        // One row per phone: a phone that changes account moves to the new one.
        PushDevice::updateOrCreate(['token' => $data['token']], [
            'user_id'         => $request->user()->id,
            'platform'        => $data['platform'] ?? 'android',
            'access_token_id' => $request->user()->currentAccessToken()?->id,
            'last_seen_at'    => now(),
        ]);

        return response()->json(['message' => 'Notifications are on for this phone.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string|max:512']);
        PushDevice::where('user_id', $request->user()->id)->where('token', $request->token)->delete();

        return response()->json(['message' => 'Notifications are off for this phone.']);
    }
}
