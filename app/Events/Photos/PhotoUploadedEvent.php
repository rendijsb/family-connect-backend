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

class PhotoUploadedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Photo $photo,
        public User $uploader,
        public string $familySlug
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("family.{$this->familySlug}.photos"),
            new PrivateChannel("family.{$this->familySlug}.album.{$this->photo->album_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'photo.uploaded';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'photo.uploaded',
            'data' => [
                'albumId' => $this->photo->album_id,
                'photo' => [
                    'id' => $this->photo->id,
                    'filename' => $this->photo->filename,
                    'originalName' => $this->photo->original_name,
                    'path' => $this->photo->path,
                    'thumbnailPath' => $this->photo->thumbnail_path,
                    'size' => $this->photo->size,
                    'width' => $this->photo->width,
                    'height' => $this->photo->height,
                    'description' => $this->photo->description,
                    'createdAt' => $this->photo->created_at->toISOString(),
                ],
                'uploaderName' => $this->uploader->name,
                'uploaderId' => $this->uploader->id,
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}