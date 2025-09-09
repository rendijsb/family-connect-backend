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

class PhotoDeletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $photoId,
        public int $albumId,
        public array $photoData,
        public User $deletedBy,
        public string $familySlug
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("family.{$this->familySlug}.photos"),
            new PrivateChannel("family.{$this->familySlug}.album.{$this->albumId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'photo.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'photo.deleted',
            'data' => [
                'photoId' => $this->photoId,
                'albumId' => $this->albumId,
                'photo' => $this->photoData,
                'deletedBy' => $this->deletedBy->name,
                'deletedById' => $this->deletedBy->id,
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}