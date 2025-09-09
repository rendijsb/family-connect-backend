<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string|max:255',
                'platform' => 'nullable|array',
                'device_type' => 'required|string|in:ios,android,web'
            ]);

            $user = $request->user();

            // Remove old tokens for this user and device type
            DeviceToken::where('user_id', $user->getId())
                ->where('device_type', $validated['device_type'])
                ->delete();

            // Create new device token
            $deviceToken = DeviceToken::create([
                'user_id' => $user->getId(),
                'token' => $validated['token'],
                'device_type' => $validated['device_type'],
                'platform_data' => $validated['platform'] ?? null,
                'is_active' => true,
                'last_used_at' => now()
            ]);

            Log::info('Device token registered', [
                'user_id' => $user->getId(),
                'device_type' => $validated['device_type'],
                'token_id' => $deviceToken->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device token registered successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to register device token', [
                'user_id' => $request->user()?->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register device token'
            ], 500);
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'device_type' => 'required|string|in:ios,android,web'
            ]);

            $user = $request->user();

            $deleted = DeviceToken::where('user_id', $user->getId())
                ->where('token', $validated['token'])
                ->where('device_type', $validated['device_type'])
                ->delete();

            Log::info('Device token removed', [
                'user_id' => $user->getId(),
                'device_type' => $validated['device_type'],
                'deleted_count' => $deleted
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device token removed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove device token', [
                'user_id' => $request->user()?->getId(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove device token'
            ], 500);
        }
    }
}