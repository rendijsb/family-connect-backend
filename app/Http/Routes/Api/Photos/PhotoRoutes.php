<?php

declare(strict_types=1);

namespace App\Http\Routes\Api\Photos;

use Illuminate\Support\Facades\Route;

class PhotoRoutes
{
    public static function api(): void
    {
        Route::prefix('families/{family:slug}/albums')->middleware('auth:sanctum')->group(function () {
            // Album routes
            Route::get('/', 'PhotoAlbumController@index');
            Route::post('/', 'PhotoAlbumController@store');
            Route::get('/{album}', 'PhotoAlbumController@show');
            Route::put('/{album}', 'PhotoAlbumController@update');
            Route::delete('/{album}', 'PhotoAlbumController@destroy');
            
            // Photo routes within albums
            Route::get('/{album}/photos', 'PhotoController@index');
            Route::post('/{album}/photos', 'PhotoController@store');
            Route::post('/{album}/photos/bulk', 'PhotoController@bulkUpload');
            Route::get('/photos/{photo}', 'PhotoController@show');
            Route::put('/photos/{photo}', 'PhotoController@update');
            Route::delete('/photos/{photo}', 'PhotoController@destroy');
            
            // Photo interactions
            Route::post('/photos/{photo}/like', 'PhotoController@like');
            Route::delete('/photos/{photo}/like', 'PhotoController@unlike');
            
            // Photo comments
            Route::get('/photos/{photo}/comments', 'PhotoCommentController@index');
            Route::post('/photos/{photo}/comments', 'PhotoCommentController@store');
            Route::put('/comments/{comment}', 'PhotoCommentController@update');
            Route::delete('/comments/{comment}', 'PhotoCommentController@destroy');
        });

        // Additional photo routes (for quick access)
        Route::prefix('photos')->middleware('auth:sanctum')->group(function () {
            Route::get('/recent', 'PhotoController@recent');
            Route::get('/favorites', 'PhotoController@favorites');
            Route::get('/tagged/{user}', 'PhotoController@taggedUser');
            Route::get('/search', 'PhotoController@search');
        });
    }
}