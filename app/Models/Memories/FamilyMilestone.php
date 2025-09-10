<?php

declare(strict_types=1);

namespace App\Models\Memories;

use App\Enums\Memories\MilestoneTypeEnum;
use App\Models\Families\Family;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class FamilyMilestone extends Model
{
    use HasFactory;

    public const TABLE = 'family_milestones';
    protected $table = self::TABLE;

    public const ID = 'id';
    public const FAMILY_ID = 'family_id';
    public const USER_ID = 'user_id';
    public const CREATED_BY = 'created_by';
    public const TYPE = 'type';
    public const TITLE = 'title';
    public const DESCRIPTION = 'description';
    public const MILESTONE_DATE = 'milestone_date';
    public const MEDIA = 'media';
    public const METADATA = 'metadata';
    public const IS_RECURRING = 'is_recurring';
    public const RECURRENCE_PATTERN = 'recurrence_pattern';
    public const NOTIFY_FAMILY = 'notify_family';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        self::FAMILY_ID,
        self::USER_ID,
        self::CREATED_BY,
        self::TYPE,
        self::TITLE,
        self::DESCRIPTION,
        self::MILESTONE_DATE,
        self::MEDIA,
        self::METADATA,
        self::IS_RECURRING,
        self::RECURRENCE_PATTERN,
        self::NOTIFY_FAMILY,
    ];

    protected $casts = [
        self::MILESTONE_DATE => 'date',
        self::MEDIA => 'array',
        self::METADATA => 'array',
        self::IS_RECURRING => 'boolean',
        self::NOTIFY_FAMILY => 'boolean',
        self::TYPE => MilestoneTypeEnum::class,
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    // Relations
    const FAMILY_RELATION = 'familyRelation';
    const USER_RELATION = 'userRelation';
    const CREATOR_RELATION = 'creatorRelation';

    public function familyRelation(): BelongsTo
    {
        return $this->belongsTo(Family::class, self::FAMILY_ID, Family::ID);
    }

    public function userRelation(): BelongsTo
    {
        return $this->belongsTo(User::class, self::USER_ID, User::ID);
    }

    public function creatorRelation(): BelongsTo
    {
        return $this->belongsTo(User::class, self::CREATED_BY, User::ID);
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

    public function getUserId(): ?int
    {
        return $this->getAttribute(self::USER_ID);
    }

    public function getCreatedBy(): int
    {
        return $this->getAttribute(self::CREATED_BY);
    }

    public function getType(): MilestoneTypeEnum
    {
        return $this->getAttribute(self::TYPE);
    }

    public function getTitle(): string
    {
        return $this->getAttribute(self::TITLE);
    }

    public function getDescription(): ?string
    {
        return $this->getAttribute(self::DESCRIPTION);
    }

    public function getMilestoneDate(): \DateTime
    {
        return $this->getAttribute(self::MILESTONE_DATE);
    }

    public function getMedia(): array
    {
        return $this->getAttribute(self::MEDIA) ?? [];
    }

    public function getMetadata(): array
    {
        return $this->getAttribute(self::METADATA) ?? [];
    }

    public function getIsRecurring(): bool
    {
        return $this->getAttribute(self::IS_RECURRING);
    }

    public function getRecurrencePattern(): ?string
    {
        return $this->getAttribute(self::RECURRENCE_PATTERN);
    }

    public function getNotifyFamily(): bool
    {
        return $this->getAttribute(self::NOTIFY_FAMILY);
    }

    // Scopes
    public function scopeForFamily(Builder $query, Family $family): Builder
    {
        return $query->where(self::FAMILY_ID, $family->getId());
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(self::USER_ID, $user->getId());
    }

    public function scopeByType(Builder $query, MilestoneTypeEnum $type): Builder
    {
        return $query->where(self::TYPE, $type);
    }

    public function scopeUpcoming(Builder $query, int $days = 30): Builder
    {
        return $query->where(self::MILESTONE_DATE, '>=', now())
                     ->where(self::MILESTONE_DATE, '<=', now()->addDays($days))
                     ->orderBy(self::MILESTONE_DATE);
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where(self::IS_RECURRING, true);
    }
}