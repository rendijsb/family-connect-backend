<?php

declare(strict_types=1);

namespace App\Events\Photos;

use App\Models\Photos\PhotoAlbum;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlbumStatsUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PhotoAlbum $album,
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
        return 'album.stats.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'album.stats.updated',
            'data' => [
                'albumId' => $this->album->id,
                'stats' => [
                    'photoCount' => $this->album->photo_count,
                    'videoCount' => $this->album->video_count,
                    'totalSize' => $this->album->total_size,
                    'lastUpdatedAt' => $this->album->last_updated_at?->toISOString(),
                ]
            ]
        ];
    }

    public function broadcastQueue(): string
    {
        return 'photos';
    }
}