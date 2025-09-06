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
        $this->app->singleton(\Pusher\Pusher::class, function ($app) {
            return new \Pusher\Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                config('broadcasting.connections.pusher.options')
            );
        });
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
