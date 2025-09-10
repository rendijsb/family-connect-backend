<?php

declare(strict_types=1);

namespace App\Models\Memories;

use App\Enums\Memories\MemoryTypeEnum;
use App\Enums\Memories\MemoryVisibilityEnum;
use App\Models\Families\Family;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin Builder
 */
class Memory extends Model
{
    use HasFactory;

    public const TABLE = 'memories';
    protected $table = self::TABLE;

    public const ID = 'id';
    public const FAMILY_ID = 'family_id';
    public const CREATED_BY = 'created_by';
    public const TITLE = 'title';
    public const DESCRIPTION = 'description';
    public const TYPE = 'type';
    public const MEMORY_DATE = 'memory_date';
    public const PARTICIPANTS = 'participants';
    public const MEDIA = 'media';
    public const LOCATION = 'location';
    public const TAGS = 'tags';
    public const VISIBILITY = 'visibility';
    public const VISIBLE_TO = 'visible_to';
    public const IS_FEATURED = 'is_featured';
    public const VIEWS_COUNT = 'views_count';
    public const LIKES_COUNT = 'likes_count';
    public const COMMENTS_COUNT = 'comments_count';
    public const AI_GENERATED_TAGS = 'ai_generated_tags';
    public const AI_DETECTED_EMOTIONS = 'ai_detected_emotions';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        self::FAMILY_ID,
        self::CREATED_BY,
        self::TITLE,
        self::DESCRIPTION,
        self::TYPE,
        self::MEMORY_DATE,
        self::PARTICIPANTS,
        self::MEDIA,
        self::LOCATION,
        self::TAGS,
        self::VISIBILITY,
        self::VISIBLE_TO,
        self::IS_FEATURED,
        self::VIEWS_COUNT,
        self::LIKES_COUNT,
        self::COMMENTS_COUNT,
        self::AI_GENERATED_TAGS,
        self::AI_DETECTED_EMOTIONS,
    ];

    protected $casts = [
        self::MEMORY_DATE => 'date',
        self::PARTICIPANTS => 'array',
        self::MEDIA => 'array',
        self::LOCATION => 'array',
        self::TAGS => 'array',
        self::VISIBLE_TO => 'array',
        self::IS_FEATURED => 'boolean',
        self::VIEWS_COUNT => 'integer',
        self::LIKES_COUNT => 'integer',
        self::COMMENTS_COUNT => 'integer',
        self::AI_GENERATED_TAGS => 'array',
        self::AI_DETECTED_EMOTIONS => 'array',
        self::TYPE => MemoryTypeEnum::class,
        self::VISIBILITY => MemoryVisibilityEnum::class,
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    // Relations
    const FAMILY_RELATION = 'familyRelation';
    const CREATOR_RELATION = 'creatorRelation';
    const COMMENTS_RELATION = 'commentsRelation';
    const LIKES_RELATION = 'likesRelation';

    public function familyRelation(): BelongsTo
    {
        return $this->belongsTo(Family::class, self::FAMILY_ID, Family::ID);
    }

    public function creatorRelation(): BelongsTo
    {
        return $this->belongsTo(User::class, self::CREATED_BY, User::ID);
    }

    public function commentsRelation(): HasMany
    {
        return $this->hasMany(MemoryComment::class, MemoryComment::MEMORY_ID, self::ID);
    }

    public function likesRelation(): HasMany
    {
        return $this->hasMany(MemoryLike::class, MemoryLike::MEMORY_ID, self::ID);
    }

    // Accessors
    public function getId(): int
    {
        return $this->getAttribute(self::ID);
    }

    public function getFamilyId(): int
    {
        return $this->getAttribute(self::FAMILY_ID);
    }

    public function getCreatedBy(): int
    {
        return $this->getAttribute(self::CREATED_BY);
    }

    public function getTitle(): string
    {
        return $this->getAttribute(self::TITLE);
    }

    public function getDescription(): ?string
    {
        return $this->getAttribute(self::DESCRIPTION);
    }

    public function getType(): MemoryTypeEnum
    {
        return $this->getAttribute(self::TYPE);
    }

    public function getMemoryDate(): \DateTime
    {
        return $this->getAttribute(self::MEMORY_DATE);
    }

    public function getParticipants(): array
    {
        return $this->getAttribute(self::PARTICIPANTS) ?? [];
    }

    public function getMedia(): array
    {
        return $this->getAttribute(self::MEDIA) ?? [];
    }

    public function getLocation(): array
    {
        return $this->getAttribute(self::LOCATION) ?? [];
    }

    public function getTags(): array
    {
        return $this->getAttribute(self::TAGS) ?? [];
    }

    public function getVisibility(): MemoryVisibilityEnum
    {
        return $this->getAttribute(self::VISIBILITY);
    }

    public function getVisibleTo(): array
    {
        return $this->getAttribute(self::VISIBLE_TO) ?? [];
    }

    public function getIsFeatured(): bool
    {
        return $this->getAttribute(self::IS_FEATURED);
    }

    public function getViewsCount(): int
    {
        return $this->getAttribute(self::VIEWS_COUNT);
    }

    public function getLikesCount(): int
    {
        return $this->getAttribute(self::LIKES_COUNT);
    }

    public function getCommentsCount(): int
    {
        return $this->getAttribute(self::COMMENTS_COUNT);
    }

    // Helper methods
    public function incrementViews(): void
    {
        $this->increment(self::VIEWS_COUNT);
    }

    public function updateLikesCount(): void
    {
        $this->update([
            self::LIKES_COUNT => $this->likesRelation()->count(),
        ]);
    }

    public function updateCommentsCount(): void
    {
        $this->update([
            self::COMMENTS_COUNT => $this->commentsRelation()->count(),
        ]);
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likesRelation()->where(MemoryLike::USER_ID, $user->getId())->exists();
    }

    public function canBeViewedBy(User $user): bool
    {
        return match ($this->getVisibility()) {
            MemoryVisibilityEnum::FAMILY => $user->families()->where('families.id', $this->getFamilyId())->exists(),
            MemoryVisibilityEnum::SPECIFIC_MEMBERS => in_array($user->getId(), $this->getVisibleTo()),
            MemoryVisibilityEnum::PRIVATE => $user->getId() === $this->getCreatedBy(),
            MemoryVisibilityEnum::PUBLIC => true,
        };
    }

    // Scopes
    public function scopeForFamily(Builder $query, Family $family): Builder
    {
        return $query->where(self::FAMILY_ID, $family->getId());
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where(self::VISIBILITY, MemoryVisibilityEnum::FAMILY)
              ->whereHas('familyRelation.membersRelation', function ($familyQuery) use ($user) {
                  $familyQuery->where('user_id', $user->getId());
              })
              ->orWhere(function (Builder $subQ) use ($user) {
                  $subQ->where(self::VISIBILITY, MemoryVisibilityEnum::SPECIFIC_MEMBERS)
                       ->whereJsonContains(self::VISIBLE_TO, $user->getId());
              })
              ->orWhere(function (Builder $subQ) use ($user) {
                  $subQ->where(self::VISIBILITY, MemoryVisibilityEnum::PRIVATE)
                       ->where(self::CREATED_BY, $user->getId());
              })
              ->orWhere(self::VISIBILITY, MemoryVisibilityEnum::PUBLIC);
        });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where(self::IS_FEATURED, true);
    }

    public function scopeByType(Builder $query, MemoryTypeEnum $type): Builder
    {
        return $query->where(self::TYPE, $type);
    }
}