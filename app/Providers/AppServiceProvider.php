<?php

namespace App\Providers;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatRoom;
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

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('message', function (string $value) {
            return ChatMessage::with(ChatMessage::CHAT_ROOM_RELATION)->findOrFail($value);
        });

        Route::bind('room', function (string $value) {
            return ChatRoom::with(['membersRelation', 'familyRelation'])->findOrFail($value);
        });

        require base_path('routes/channels.php');
    }
}
