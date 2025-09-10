<?php

declare(strict_types=1);

namespace App\Models\Memories;

use App\Models\Families\Family;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class FamilyTimeCapsule extends Model
{
    use HasFactory;

    public const TABLE = 'family_time_capsules';
    protected $table = self::TABLE;

    public const ID = 'id';
    public const FAMILY_ID = 'family_id';
    public const CREATED_BY = 'created_by';
    public const TITLE = 'title';
    public const DESCRIPTION = 'description';
    public const CONTENTS = 'contents';
    public const CONTRIBUTORS = 'contributors';
    public const SEALED_AT = 'sealed_at';
    public const OPENS_AT = 'opens_at';
    public const IS_OPENED = 'is_opened';
    public const OPENED_AT = 'opened_at';
    public const OPENING_CONDITIONS = 'opening_conditions';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        self::FAMILY_ID,
        self::CREATED_BY,
        self::TITLE,
        self::DESCRIPTION,
        self::CONTENTS,
        self::CONTRIBUTORS,
        self::SEALED_AT,
        self::OPENS_AT,
        self::IS_OPENED,
        self::OPENED_AT,
        self::OPENING_CONDITIONS,
    ];

    protected $casts = [
        self::CONTENTS => 'array',
        self::CONTRIBUTORS => 'array',
        self::SEALED_AT => 'datetime',
        self::OPENS_AT => 'datetime',
        self::IS_OPENED => 'boolean',
        self::OPENED_AT => 'datetime',
        self::OPENING_CONDITIONS => 'array',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    // Relations
    const FAMILY_RELATION = 'familyRelation';
    const CREATOR_RELATION = 'creatorRelation';

    public function familyRelation(): BelongsTo
    {
        return $this->belongsTo(Family::class, self::FAMILY_ID, Family::ID);
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

    public function getContents(): array
    {
        return $this->getAttribute(self::CONTENTS) ?? [];
    }

    public function getContributors(): array
    {
        return $this->getAttribute(self::CONTRIBUTORS) ?? [];
    }

    public function getSealedAt(): \DateTime
    {
        return $this->getAttribute(self::SEALED_AT);
    }

    public function getOpensAt(): \DateTime
    {
        return $this->getAttribute(self::OPENS_AT);
    }

    public function getIsOpened(): bool
    {
        return $this->getAttribute(self::IS_OPENED);
    }

    public function getOpenedAt(): ?\DateTime
    {
        return $this->getAttribute(self::OPENED_AT);
    }

    public function getOpeningConditions(): array
    {
        return $this->getAttribute(self::OPENING_CONDITIONS) ?? [];
    }

    // Helper methods
    public function canBeOpened(): bool
    {
        if ($this->getIsOpened()) {
            return false;
        }

        // Check if opening date has passed
        if ($this->getOpensAt() > now()) {
            return false;
        }

        // Check custom opening conditions if any
        $conditions = $this->getOpeningConditions();
        if (!empty($conditions)) {
            // Custom logic for conditions can be implemented here
            // For now, just check the date
            return true;
        }

        return true;
    }

    public function open(User $user): bool
    {
        if (!$this->canBeOpened()) {
            return false;
        }

        $this->update([
            self::IS_OPENED => true,
            self::OPENED_AT => now(),
        ]);

        return true;
    }

    public function addContent(array $content, User $contributor): void
    {
        if ($this->getIsOpened()) {
            throw new \Exception('Cannot add content to an opened time capsule');
        }

        $contents = $this->getContents();
        $contents[] = array_merge($content, [
            'contributor_id' => $contributor->getId(),
            'added_at' => now()->toISOString(),
        ]);

        $contributors = $this->getContributors();
        if (!in_array($contributor->getId(), $contributors)) {
            $contributors[] = $contributor->getId();
        }

        $this->update([
            self::CONTENTS => $contents,
            self::CONTRIBUTORS => $contributors,
        ]);
    }

    public function getDaysUntilOpening(): int
    {
        if ($this->getIsOpened()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->getOpensAt(), false));
    }

    // Scopes
    public function scopeForFamily(Builder $query, Family $family): Builder
    {
        return $query->where(self::FAMILY_ID, $family->getId());
    }

    public function scopeSealed(Builder $query): Builder
    {
        return $query->where(self::IS_OPENED, false);
    }

    public function scopeOpened(Builder $query): Builder
    {
        return $query->where(self::IS_OPENED, true);
    }

    public function scopeReadyToOpen(Builder $query): Builder
    {
        return $query->sealed()
                     ->where(self::OPENS_AT, '<=', now());
    }

    public function scopeOpeningSoon(Builder $query, int $days = 7): Builder
    {
        return $query->sealed()
                     ->where(self::OPENS_AT, '<=', now()->addDays($days))
                     ->where(self::OPENS_AT, '>', now());
    }
}