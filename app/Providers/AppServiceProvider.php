<?php

namespace App\Providers;

use App\Models\Chat\ChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure route model binding for ChatMessage to always load the chatRoomRelation
        Route::bind('message', function (string $value) {
            return ChatMessage::with(ChatMessage::CHAT_ROOM_RELATION)->findOrFail($value);
        });
    }
}
