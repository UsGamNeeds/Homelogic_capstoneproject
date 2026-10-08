<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpoPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExpoPushTokenController extends Controller
{
    /**
     * Register the mobile app's Expo push token for the authenticated user.
     *
     * A token identifies a device, not a person. When a shared phone signs in as
     * someone else the token moves to the new user, so the previous caregiver
     * stops receiving that device's alerts.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[.+\]$/'],
            'platform' => 'nullable|string|in:ios,android',
        ]);

        $user = $request->user();

        $token = ExpoPushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'] ?? null,
            ]
        );

        Log::info("[ExpoPushToken] Stored for user {$user->id}");

        return response()->json([
            'message' => 'Push token saved.',
            'token' => ['id' => $token->id],
        ], 201);
    }

    /**
     * Remove a push token (on sign-out).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $deleted = ExpoPushToken::where('user_id', $request->user()->id)
            ->where('token', $request->input('token'))
            ->delete();

        if ($deleted) {
            Log::info('[ExpoPushToken] Removed for user ' . $request->user()->id);
        }

        return response()->json(['message' => 'Push token removed.'], 200);
    }
}
