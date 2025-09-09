<?php

declare(strict_types=1);

namespace App\Models\Photos;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'photo_id',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::created(function (PhotoLike $like) {
            $like->photo->updateLikesCount();
        });

        static::deleted(function (PhotoLike $like) {
            $like->photo->updateLikesCount();
        });
    }
}