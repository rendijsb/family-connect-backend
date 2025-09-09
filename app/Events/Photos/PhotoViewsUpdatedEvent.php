<?php

declare(strict_types=1);

namespace App\Events\Photos;

use App\Models\Photos\Photo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotoViewsUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Photo $photo,
        public string $familySlug
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("family.{$this->familySlug}.photo.{$this->photo->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'photo.views.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'photo.views.updated',
            'data' => [
                'photoId' => $this->photo->id,
                'viewsCount' => $this->photo->views_count,
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}