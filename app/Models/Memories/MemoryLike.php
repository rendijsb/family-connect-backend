<?php

declare(strict_types=1);

namespace App\Models\Memories;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class MemoryLike extends Model
{
    use HasFactory;

    public const TABLE = 'memory_likes';
    protected $table = self::TABLE;

    public const ID = 'id';
    public const MEMORY_ID = 'memory_id';
    public const USER_ID = 'user_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        self::MEMORY_ID,
        self::USER_ID,
    ];

    protected $casts = [
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    // Relations
    const MEMORY_RELATION = 'memoryRelation';
    const USER_RELATION = 'userRelation';

    public function memoryRelation(): BelongsTo
    {
        return $this->belongsTo(Memory::class, self::MEMORY_ID, Memory::ID);
    }

    public function userRelation(): BelongsTo
    {
        return $this->belongsTo(User::class, self::USER_ID, User::ID);
    }

    // Accessors
    public function getId(): int
    {
        return $this->getAttribute(self::ID);
    }

    public function getMemoryId(): int
    {
        return $this->getAttribute(self::MEMORY_ID);
    }

    public function getUserId(): int
    {
        return $this->getAttribute(self::USER_ID);
    }

    // Unique constraint
    protected static function booted(): void
    {
        static::creating(function (MemoryLike $like) {
            // Prevent duplicate likes
            $existing = self::where(self::MEMORY_ID, $like->getMemoryId())
                           ->where(self::USER_ID, $like->getUserId())
                           ->exists();
            
            if ($existing) {
                throw new \Exception('User has already liked this memory');
            }
        });

        static::created(function (MemoryLike $like) {
            $like->memoryRelation->updateLikesCount();
        });

        static::deleted(function (MemoryLike $like) {
            $like->memoryRelation->updateLikesCount();
        });
    }
}