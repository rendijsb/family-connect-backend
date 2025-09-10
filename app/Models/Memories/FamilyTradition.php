<?php

declare(strict_types=1);

namespace App\Models\Memories;

use App\Enums\Memories\TraditionFrequencyEnum;
use App\Models\Families\Family;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder
 */
class FamilyTradition extends Model
{
    use HasFactory;

    public const TABLE = 'family_traditions';
    protected $table = self::TABLE;

    public const ID = 'id';
    public const FAMILY_ID = 'family_id';
    public const CREATED_BY = 'created_by';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const FREQUENCY = 'frequency';
    public const SCHEDULE_DETAILS = 'schedule_details';
    public const STARTED_DATE = 'started_date';
    public const PARTICIPANTS = 'participants';
    public const ACTIVITIES = 'activities';
    public const RECIPES = 'recipes';
    public const SONGS_GAMES = 'songs_games';
    public const MEDIA = 'media';
    public const IS_ACTIVE = 'is_active';
    public const TIMES_CELEBRATED = 'times_celebrated';
    public const LAST_CELEBRATED_AT = 'last_celebrated_at';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        self::FAMILY_ID,
        self::CREATED_BY,
        self::NAME,
        self::DESCRIPTION,
        self::FREQUENCY,
        self::SCHEDULE_DETAILS,
        self::STARTED_DATE,
        self::PARTICIPANTS,
        self::ACTIVITIES,
        self::RECIPES,
        self::SONGS_GAMES,
        self::MEDIA,
        self::IS_ACTIVE,
        self::TIMES_CELEBRATED,
        self::LAST_CELEBRATED_AT,
    ];

    protected $casts = [
        self::STARTED_DATE => 'date',
        self::SCHEDULE_DETAILS => 'array',
        self::PARTICIPANTS => 'array',
        self::ACTIVITIES => 'array',
        self::RECIPES => 'array',
        self::SONGS_GAMES => 'array',
        self::MEDIA => 'array',
        self::IS_ACTIVE => 'boolean',
        self::TIMES_CELEBRATED => 'integer',
        self::LAST_CELEBRATED_AT => 'datetime',
        self::FREQUENCY => TraditionFrequencyEnum::class,
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

    public function getName(): string
    {
        return $this->getAttribute(self::NAME);
    }

    public function getDescription(): string
    {
        return $this->getAttribute(self::DESCRIPTION);
    }

    public function getFrequency(): TraditionFrequencyEnum
    {
        return $this->getAttribute(self::FREQUENCY);
    }

    public function getScheduleDetails(): array
    {
        return $this->getAttribute(self::SCHEDULE_DETAILS) ?? [];
    }

    public function getStartedDate(): ?\DateTime
    {
        return $this->getAttribute(self::STARTED_DATE);
    }

    public function getParticipants(): array
    {
        return $this->getAttribute(self::PARTICIPANTS) ?? [];
    }

    public function getActivities(): array
    {
        return $this->getAttribute(self::ACTIVITIES) ?? [];
    }

    public function getRecipes(): array
    {
        return $this->getAttribute(self::RECIPES) ?? [];
    }

    public function getSongsGames(): array
    {
        return $this->getAttribute(self::SONGS_GAMES) ?? [];
    }

    public function getMedia(): array
    {
        return $this->getAttribute(self::MEDIA) ?? [];
    }

    public function getIsActive(): bool
    {
        return $this->getAttribute(self::IS_ACTIVE);
    }

    public function getTimesCelebrated(): int
    {
        return $this->getAttribute(self::TIMES_CELEBRATED);
    }

    public function getLastCelebratedAt(): ?\DateTime
    {
        return $this->getAttribute(self::LAST_CELEBRATED_AT);
    }

    // Helper methods
    public function celebrate(): void
    {
        $this->increment(self::TIMES_CELEBRATED);
        $this->update([self::LAST_CELEBRATED_AT => now()]);
    }

    public function deactivate(): void
    {
        $this->update([self::IS_ACTIVE => false]);
    }

    public function activate(): void
    {
        $this->update([self::IS_ACTIVE => true]);
    }

    // Scopes
    public function scopeForFamily(Builder $query, Family $family): Builder
    {
        return $query->where(self::FAMILY_ID, $family->getId());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(self::IS_ACTIVE, true);
    }

    public function scopeByFrequency(Builder $query, TraditionFrequencyEnum $frequency): Builder
    {
        return $query->where(self::FREQUENCY, $frequency);
    }

    public function scopeNeedingCelebration(Builder $query): Builder
    {
        return $query->active()->where(function (Builder $q) {
            $q->whereNull(self::LAST_CELEBRATED_AT)
              ->orWhere(self::LAST_CELEBRATED_AT, '<', $this->getNextCelebrationDate());
        });
    }

    public function getNextCelebrationDate(): ?\DateTime
    {
        if (!$this->getLastCelebratedAt()) {
            return now();
        }

        return match ($this->getFrequency()) {
            TraditionFrequencyEnum::DAILY => $this->getLastCelebratedAt()->addDay(),
            TraditionFrequencyEnum::WEEKLY => $this->getLastCelebratedAt()->addWeek(),
            TraditionFrequencyEnum::MONTHLY => $this->getLastCelebratedAt()->addMonth(),
            TraditionFrequencyEnum::YEARLY => $this->getLastCelebratedAt()->addYear(),
            default => null,
        };
    }
}