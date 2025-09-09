<?php

use Ably\AblyRest;
use App\Http\Controllers\Broadcasting\BroadcastController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\TestNotificationController;
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

Route::get('/broadcasting/auth', function (Request $request) {
    $ably = new AblyRest(env('ABLY_KEY'));

    $tokenRequest = $ably->auth->createTokenRequest([], [
        'clientId' => (string) $request->user()->id,
    ]);

    return response()->json($tokenRequest);
})->middleware('auth:sanctum');

// Device token routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
    
    // Debug/Test routes (remove in production)
    Route::post('/test-notification', [TestNotificationController::class, 'testNotification']);
    Route::get('/device-tokens/list', [TestNotificationController::class, 'getDeviceTokens']);
    Route::get('/notification-config', [TestNotificationController::class, 'checkConfig']);
});

AuthRoutes::api();
FamilyRoutes::api();
FamilyMemberRoutes::api();
ChatRoutes::api();
