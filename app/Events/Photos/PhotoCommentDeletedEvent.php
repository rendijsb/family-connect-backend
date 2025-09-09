<?php

declare(strict_types=1);

namespace App\Events\Photos;

use App\Models\Photos\Photo;
use App\Models\Photos\PhotoComment;
use App\Models\Users\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotoCommentDeletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Photo $photo,
        public int $commentId,
        public User $user,
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
        return 'photo.comment.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'photo.comment.deleted',
            'data' => [
                'photoId' => $this->photo->id,
                'commentId' => $this->commentId,
                'commentsCount' => $this->photo->comments_count,
                'deletedBy' => $this->user->name,
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}