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

class PhotoCommentedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Photo $photo,
        public PhotoComment $comment,
        public User $commenter,
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
        return 'photo.commented';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'photo.commented',
            'data' => [
                'photoId' => $this->photo->id,
                'comment' => [
                    'id' => $this->comment->id,
                    'userId' => $this->comment->user_id,
                    'userName' => $this->commenter->name,
                    'userAvatar' => $this->commenter->avatar,
                    'comment' => $this->comment->comment,
                    'parentId' => $this->comment->parent_id,
                    'createdAt' => $this->comment->created_at->toISOString(),
                    'isEdited' => $this->comment->is_edited,
                ],
                'commentsCount' => $this->photo->comments_count,
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}