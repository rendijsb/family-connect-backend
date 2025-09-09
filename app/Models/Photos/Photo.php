<?php

declare(strict_types=1);

namespace App\Models\Photos;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Photo extends Model
{
    use HasFactory;

    protected $fillable = [
        'album_id',
        'uploaded_by',
        'filename',
        'original_name',
        'mime_type',
        'path',
        'thumbnail_path',
        'size',
        'width',
        'height',
        'metadata',
        'description',
        'tags',
        'people_tagged',
        'location',
        'taken_at',
        'views_count',
        'likes_count',
        'comments_count',
        'is_favorite',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tags' => 'array',
        'people_tagged' => 'array',
        'taken_at' => 'datetime',
        'views_count' => 'integer',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'is_favorite' => 'boolean',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function album(): BelongsTo
    {
        return $this->belongsTo(PhotoAlbum::class, 'album_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PhotoComment::class);
    }

    public function topLevelComments(): HasMany
    {
        return $this->comments()->whereNull('parent_id')->orderBy('created_at', 'desc');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PhotoLike::class);
    }

    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'photo_likes', 'photo_id', 'user_id')
                    ->withTimestamps();
    }

    public function taggedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'photo_tags', 'photo_id', 'user_id')
                    ->withTimestamps();
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function updateLikesCount(): void
    {
        $this->update([
            'likes_count' => $this->likes()->count(),
        ]);
    }

    public function updateCommentsCount(): void
    {
        $this->update([
            'comments_count' => $this->comments()->count(),
        ]);
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function getFullUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->thumbnail_path 
            ? asset('storage/' . $this->thumbnail_path)
            : $this->full_url;
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    protected static function booted(): void
    {
        static::created(function (Photo $photo) {
            $photo->album->updateStats();
        });

        static::deleted(function (Photo $photo) {
            $photo->album->updateStats();
        });
    }
}