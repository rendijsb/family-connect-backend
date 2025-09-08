<?php

use Ably\AblyRest;
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

Route::post('/broadcasting/auth', function (Request $request) {
    $ably = new AblyRest(env('ABLY_KEY'));

    return $ably->auth->createTokenRequest([], [
        'clientId' => $request->user()->id ?? 'guest',
    ]);
})->middleware('auth:sanctum');

AuthRoutes::api();
FamilyRoutes::api();
FamilyMemberRoutes::api();
ChatRoutes::api();
