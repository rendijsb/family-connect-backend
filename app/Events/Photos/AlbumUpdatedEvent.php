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

class AlbumUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PhotoAlbum $album,
        public array $changes,
        public User $updatedBy,
        public string $familySlug
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("family.{$this->familySlug}.photos"),
            new PrivateChannel("family.{$this->familySlug}.album.{$this->album->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'album.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'album.updated',
            'data' => [
                'albumId' => $this->album->id,
                'changes' => $this->changes,
                'updatedBy' => $this->updatedBy->name,
                'updatedById' => $this->updatedBy->id,
                'updatedAt' => $this->album->updated_at->toISOString(),
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}