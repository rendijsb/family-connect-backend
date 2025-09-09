<?php

use App\Http\Controllers\Api\Photos\PhotoAlbumController;
use App\Http\Controllers\Api\Photos\PhotoCommentController;
use App\Http\Controllers\Api\Photos\PhotoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Photo API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Photo API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->prefix('families/{family}')->group(function () {
    
    // Photo Album Routes
    Route::get('albums', [PhotoAlbumController::class, 'index']);
    Route::post('albums', [PhotoAlbumController::class, 'store']);
    Route::get('albums/{album}', [PhotoAlbumController::class, 'show']);
    Route::put('albums/{album}', [PhotoAlbumController::class, 'update']);
    Route::delete('albums/{album}', [PhotoAlbumController::class, 'destroy']);
    Route::get('albums/{album}/stats', [PhotoAlbumController::class, 'stats']);
    Route::post('albums/{album}/cover-photo', [PhotoAlbumController::class, 'setCoverPhoto']);
    
    // Photo Routes within Albums
    Route::prefix('albums/{album}')->group(function () {
        Route::get('photos', [PhotoController::class, 'index']);
        Route::post('photos', [PhotoController::class, 'store']);
        Route::get('photos/{photo}', [PhotoController::class, 'show']);
        Route::put('photos/{photo}', [PhotoController::class, 'update']);
        Route::delete('photos/{photo}', [PhotoController::class, 'destroy']);
        
        // Photo Actions
        Route::post('photos/{photo}/like', [PhotoController::class, 'like']);
        Route::post('photos/{photo}/views', [PhotoController::class, 'incrementViews']);
        Route::get('photos/{photo}/download', [PhotoController::class, 'download']);
        
        // Photo Comments
        Route::get('photos/{photo}/comments', [PhotoCommentController::class, 'index']);
        Route::post('photos/{photo}/comments', [PhotoCommentController::class, 'store']);
    });
    
    // Bulk Operations
    Route::post('photos/download-bulk', [PhotoController::class, 'downloadBulk']);
    
    // Comment Management (for editing/deleting comments)
    Route::put('comments/{comment}', [PhotoCommentController::class, 'update']);
    Route::delete('comments/{comment}', [PhotoCommentController::class, 'destroy']);
    
    // General Album Stats
    Route::get('albums-stats', [PhotoAlbumController::class, 'stats']);
});

// Additional global photo routes
Route::middleware(['auth:sanctum'])->prefix('photos')->group(function () {
    Route::get('recent', [PhotoController::class, 'recent']);
    Route::get('favorites', [PhotoController::class, 'favorites']);
    Route::get('tagged/{user}', [PhotoController::class, 'taggedUser']);
    Route::get('search', [PhotoController::class, 'search']);
});

// Public routes (for shared albums - future feature)
Route::prefix('shared')->group(function () {
    // Route::get('albums/{shareCode}', [PhotoAlbumController::class, 'showShared']);
    // Route::get('albums/{shareCode}/photos', [PhotoController::class, 'indexShared']);
});