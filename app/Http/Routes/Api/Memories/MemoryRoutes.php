<?php

declare(strict_types=1);

namespace App\Http\Routes\Api\Memories;

use Illuminate\Support\Facades\Route;

class MemoryRoutes
{
    public static function api(): void
    {
        Route::prefix('families/{family:slug}/memories')->middleware('auth:sanctum')->group(function () {
            // Memory routes
            Route::get('/', 'MemoryController@index');
            Route::post('/', 'MemoryController@store');
            Route::get('/timeline', 'MemoryController@timeline');
            Route::get('/featured', 'MemoryController@featured');
            Route::get('/{memory}', 'MemoryController@show');
            Route::put('/{memory}', 'MemoryController@update');
            Route::delete('/{memory}', 'MemoryController@destroy');
            
            // Memory interactions
            Route::post('/{memory}/like', 'MemoryController@like');
            Route::delete('/{memory}/like', 'MemoryController@unlike');
            Route::post('/{memory}/feature', 'MemoryController@feature');
            Route::delete('/{memory}/feature', 'MemoryController@unfeature');
            
            // Memory comments
            Route::get('/{memory}/comments', 'MemoryCommentController@index');
            Route::post('/{memory}/comments', 'MemoryCommentController@store');
            Route::put('/comments/{comment}', 'MemoryCommentController@update');
            Route::delete('/comments/{comment}', 'MemoryCommentController@destroy');
            
            // Milestones
            Route::get('/milestones', 'MilestoneController@index');
            Route::post('/milestones', 'MilestoneController@store');
            Route::get('/milestones/upcoming', 'MilestoneController@upcoming');
            Route::get('/milestones/{milestone}', 'MilestoneController@show');
            Route::put('/milestones/{milestone}', 'MilestoneController@update');
            Route::delete('/milestones/{milestone}', 'MilestoneController@destroy');
            
            // Traditions
            Route::get('/traditions', 'TraditionController@index');
            Route::post('/traditions', 'TraditionController@store');
            Route::get('/traditions/active', 'TraditionController@active');
            Route::get('/traditions/{tradition}', 'TraditionController@show');
            Route::put('/traditions/{tradition}', 'TraditionController@update');
            Route::delete('/traditions/{tradition}', 'TraditionController@destroy');
            Route::post('/traditions/{tradition}/celebrate', 'TraditionController@celebrate');
            Route::post('/traditions/{tradition}/activate', 'TraditionController@activate');
            Route::post('/traditions/{tradition}/deactivate', 'TraditionController@deactivate');
            
            // Time Capsules
            Route::get('/time-capsules', 'TimeCapsuleController@index');
            Route::post('/time-capsules', 'TimeCapsuleController@store');
            Route::get('/time-capsules/sealed', 'TimeCapsuleController@sealed');
            Route::get('/time-capsules/ready-to-open', 'TimeCapsuleController@readyToOpen');
            Route::get('/time-capsules/{capsule}', 'TimeCapsuleController@show');
            Route::put('/time-capsules/{capsule}', 'TimeCapsuleController@update');
            Route::delete('/time-capsules/{capsule}', 'TimeCapsuleController@destroy');
            Route::post('/time-capsules/{capsule}/contribute', 'TimeCapsuleController@contribute');
            Route::post('/time-capsules/{capsule}/open', 'TimeCapsuleController@open');
            
            // Memory generation and exports
            Route::post('/generate-video', 'MemoryVideoController@generate');
            Route::post('/generate-book', 'MemoryBookController@generate');
            Route::get('/export', 'MemoryExportController@export');
        });

        // Global memory routes (cross-family)
        Route::prefix('memories')->middleware('auth:sanctum')->group(function () {
            Route::get('/recent', 'MemoryController@recent');
            Route::get('/favorites', 'MemoryController@favorites');
            Route::get('/search', 'MemoryController@search');
            Route::get('/stats', 'MemoryController@stats');
        });
    }
}