<?php

declare(strict_types=1);

namespace App\Events\Photos;

use App\Models\Photos\PhotoAlbum;
use App\Models\Users\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlbumCreatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PhotoAlbum $album,
        public User $creator,
        public string $familySlug
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("family.{$this->familySlug}.photos"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'album.created';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'album.created',
            'data' => [
                'album' => [
                    'id' => $this->album->id,
                    'name' => $this->album->name,
                    'description' => $this->album->description,
                    'privacy' => $this->album->privacy->value,
                    'photoCount' => $this->album->photo_count,
                    'createdAt' => $this->album->created_at->toISOString(),
                ],
                'creatorName' => $this->creator->name,
                'creatorId' => $this->creator->id,
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}