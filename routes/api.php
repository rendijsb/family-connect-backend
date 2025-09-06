<?php

use App\Http\Controllers\Broadcasting\BroadcastController;
use App\Http\Routes\Api\Auth\AuthRoutes;
use App\Http\Routes\Api\Chat\ChatRoutes;
use App\Http\Routes\Api\Family\FamilyMemberRoutes;
use App\Http\Routes\Api\Family\FamilyRoutes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();
    $user->load('roleRelation');

    return response()->json([
        'success' => true,
        'data' => $user
    ]);
});

Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])
    ->middleware('auth:sanctum');

AuthRoutes::api();
FamilyRoutes::api();
FamilyMemberRoutes::api();
ChatRoutes::api();

// Add this temporary debug route to check what the auth endpoint is actually returning:

Route::post('/debug-broadcasting-auth', function (Request $request) {
    try {
        // Get the same inputs that the real auth endpoint uses
        $socketId = $request->socket_id;
        $channelName = $request->channel_name;

        // Check what Pusher instance we're getting
        $pusher = app(\Pusher\Pusher::class);

        // Try to create the auth signature manually
        $auth = $pusher->authorizeChannel($channelName, $socketId);

        return response()->json([
            'success' => true,
            'pusher_config' => [
                'key' => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'app_id' => config('broadcasting.connections.pusher.app_id'),
            ],
            'request_data' => [
                'socket_id' => $socketId,
                'channel_name' => $channelName,
            ],
            'auth_result' => $auth,
            'raw_pusher_key' => $pusher->getSettings()['auth_key'] ?? 'not_available',
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

// Replace the previous debug route with this simpler version:

Route::post('/debug-broadcasting-simple', function (Request $request) {
    try {
        // Just check the configuration values
        return response()->json([
            'success' => true,
            'env_values' => [
                'PUSHER_APP_KEY' => env('PUSHER_APP_KEY'),
                'PUSHER_APP_SECRET' => env('PUSHER_APP_SECRET'),
                'PUSHER_APP_ID' => env('PUSHER_APP_ID'),
                'PUSHER_APP_CLUSTER' => env('PUSHER_APP_CLUSTER'),
            ],
            'config_values' => [
                'key' => config('broadcasting.connections.pusher.key'),
                'secret' => config('broadcasting.connections.pusher.secret'),
                'app_id' => config('broadcasting.connections.pusher.app_id'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            ],
            'broadcasting_default' => config('broadcasting.default'),
            'request_data' => [
                'socket_id' => $request->socket_id,
                'channel_name' => $request->channel_name,
            ],
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

// Also add this route to test the actual broadcasting auth manually:
Route::post('/debug-auth-manual', function (Request $request) {
    try {
        $socketId = $request->socket_id;
        $channelName = $request->channel_name;

        // Manual auth signature creation
        $key = config('broadcasting.connections.pusher.key');
        $secret = config('broadcasting.connections.pusher.secret');

        $string_to_sign = $socketId . ':' . $channelName;
        $signature = hash_hmac('sha256', $string_to_sign, $secret);

        return response()->json([
            'success' => true,
            'auth' => $key . ':' . $signature,
            'key_used' => $key,
            'secret_length' => strlen($secret),
            'string_signed' => $string_to_sign,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});
