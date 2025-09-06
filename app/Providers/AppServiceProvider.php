<?php

namespace App\Providers;

use App\Models\Chat\ChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Pusher\Pusher;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // Force the broadcasting manager to use correct Pusher config
        $this->app->bind('pusher', function ($app) {
            $config = config('broadcasting.connections.pusher');

            return new Pusher(
                $config['key'],
                $config['secret'],
                $config['app_id'],
                $config['options']
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
        Broadcast::routes();
        require base_path('routes/channels.php');
    }
}
