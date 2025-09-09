<?php

declare(strict_types=1);

namespace App\Models\Photos;

use App\Enums\Photos\AlbumPrivacyEnum;
use App\Models\Families\Family;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoAlbum extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'created_by',
        'name',
        'description',
        'cover_photo',
        'privacy',
        'allowed_members',
        'allow_download',
        'allow_comments',
        'photo_count',
        'video_count',
        'total_size',
        'last_updated_at',
    ];

    protected $casts = [
        'privacy' => AlbumPrivacyEnum::class,
        'allowed_members' => 'array',
        'allow_download' => 'boolean',
        'allow_comments' => 'boolean',
        'photo_count' => 'integer',
        'video_count' => 'integer',
        'total_size' => 'integer',
        'last_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'album_id');
    }

    public function recentPhotos(): HasMany
    {
        return $this->photos()->orderBy('created_at', 'desc');
    }

    public function updateStats(): void
    {
        $stats = $this->photos()
            ->selectRaw('COUNT(*) as photo_count, SUM(size) as total_size')
            ->first();

        $this->update([
            'photo_count' => $stats->photo_count ?? 0,
            'total_size' => $stats->total_size ?? 0,
            'last_updated_at' => now(),
        ]);
    }

    public function canUserAccess(User $user): bool
    {
        if ($this->privacy === AlbumPrivacyEnum::FAMILY) {
            return $user->families()->where('families.id', $this->family_id)->exists();
        }

        if ($this->privacy === AlbumPrivacyEnum::SPECIFIC_MEMBERS) {
            return in_array($user->id, $this->allowed_members ?? []);
        }

        if ($this->privacy === AlbumPrivacyEnum::PRIVATE) {
            return $user->id === $this->created_by;
        }

        return $this->privacy === AlbumPrivacyEnum::PUBLIC;
    }
}