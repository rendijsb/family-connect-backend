<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster as BasePusherBroadcaster;
use Illuminate\Support\Facades\Log;

class PusherBroadcaster extends BasePusherBroadcaster
{
    public function broadcast(array $channels, $event, array $payload = [])
    {
        try {
            return parent::broadcast($channels, $event, $payload);
        } catch (\Exception $e) {
            Log::error('Pusher broadcast failed', [
                'channels' => $channels,
                'event' => $event,
                'error' => $e->getMessage()
            ]);

            // Don't re-throw - just log the error
            return null;
        }
    }
}
