<?php

declare(strict_types=1);

namespace App\Models\Photos;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'photo_id',
        'user_id',
        'parent_id',
        'comment',
        'is_edited',
        'edited_at',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhotoComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PhotoComment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    public function allReplies(): HasMany
    {
        return $this->replies()->with('allReplies');
    }

    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }

    public function markAsEdited(): void
    {
        $this->update([
            'is_edited' => true,
            'edited_at' => now(),
        ]);
    }

    protected static function booted(): void
    {
        static::created(function (PhotoComment $comment) {
            $comment->photo->updateCommentsCount();
        });

        static::deleted(function (PhotoComment $comment) {
            $comment->photo->updateCommentsCount();
        });
    }
}