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
    public function register()
    {
        $this->app->singleton(\Pusher\Pusher::class, function ($app) {
            $config = config('broadcasting.connections.pusher');

            return new \Pusher\Pusher(
                $config['key'],           // This should be 40fb26d70f1e65939629
                $config['secret'],        // Your secret
                $config['app_id'],        // Your app ID
                $config['options'] ?? []
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
