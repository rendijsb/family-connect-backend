<?php

declare(strict_types=1);

namespace App\Events\Photos;

use App\Models\Photos\Photo;
use App\Models\Users\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotoUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Photo $photo,
        public array $changes,
        public User $updatedBy,
        public string $familySlug
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("family.{$this->familySlug}.photo.{$this->photo->id}"),
            new PrivateChannel("family.{$this->familySlug}.album.{$this->photo->album_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'photo.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'photo.updated',
            'data' => [
                'photoId' => $this->photo->id,
                'updates' => $this->changes,
                'updatedBy' => $this->updatedBy->name,
                'updatedById' => $this->updatedBy->id,
                'updatedAt' => $this->photo->updated_at->toISOString(),
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}