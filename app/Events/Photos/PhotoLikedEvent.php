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

class PhotoLikedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Photo $photo,
        public User $user,
        public string $familySlug,
        public bool $liked = true
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
        return $this->liked ? 'photo.liked' : 'photo.unliked';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->liked ? 'photo.liked' : 'photo.unliked',
            'data' => [
                'photoId' => $this->photo->id,
                'userId' => $this->user->id,
                'userName' => $this->user->name,
                'likesCount' => $this->photo->likes_count,
                'liked' => $this->liked,
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}