<?php

declare(strict_types=1);

namespace App\Models\Memories;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder
 */
class MemoryComment extends Model
{
    use HasFactory;

    public const TABLE = 'memory_comments';
    protected $table = self::TABLE;

    public const ID = 'id';
    public const MEMORY_ID = 'memory_id';
    public const USER_ID = 'user_id';
    public const PARENT_ID = 'parent_id';
    public const CONTENT = 'content';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        self::MEMORY_ID,
        self::USER_ID,
        self::PARENT_ID,
        self::CONTENT,
    ];

    protected $casts = [
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    // Relations
    const MEMORY_RELATION = 'memoryRelation';
    const USER_RELATION = 'userRelation';
    const PARENT_RELATION = 'parentRelation';
    const REPLIES_RELATION = 'repliesRelation';

    public function memoryRelation(): BelongsTo
    {
        return $this->belongsTo(Memory::class, self::MEMORY_ID, Memory::ID);
    }

    public function userRelation(): BelongsTo
    {
        return $this->belongsTo(User::class, self::USER_ID, User::ID);
    }

    public function parentRelation(): BelongsTo
    {
        return $this->belongsTo(self::class, self::PARENT_ID, self::ID);
    }

    public function repliesRelation(): HasMany
    {
        return $this->hasMany(self::class, self::PARENT_ID, self::ID);
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

    public function getParentId(): ?int
    {
        return $this->getAttribute(self::PARENT_ID);
    }

    public function getContent(): string
    {
        return $this->getAttribute(self::CONTENT);
    }

    // Scopes
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull(self::PARENT_ID);
    }

    public function scopeReplies(Builder $query): Builder
    {
        return $query->whereNotNull(self::PARENT_ID);
    }
}