<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasFactory;

    public const USER_ID = 'user_id';
    public const TOKEN = 'token';
    public const DEVICE_TYPE = 'device_type';
    public const PLATFORM_DATA = 'platform_data';
    public const IS_ACTIVE = 'is_active';
    public const LAST_USED_AT = 'last_used_at';

    protected $fillable = [
        self::USER_ID,
        self::TOKEN,
        self::DEVICE_TYPE,
        self::PLATFORM_DATA,
        self::IS_ACTIVE,
        self::LAST_USED_AT,
    ];

    protected $casts = [
        self::PLATFORM_DATA => 'array',
        self::IS_ACTIVE => 'boolean',
        self::LAST_USED_AT => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, self::USER_ID);
    }

    public function getUserId(): int
    {
        return $this->getAttribute(self::USER_ID);
    }

    public function getToken(): string
    {
        return $this->getAttribute(self::TOKEN);
    }

    public function getDeviceType(): string
    {
        return $this->getAttribute(self::DEVICE_TYPE);
    }

    public function getPlatformData(): ?array
    {
        return $this->getAttribute(self::PLATFORM_DATA);
    }

    public function isActive(): bool
    {
        return $this->getAttribute(self::IS_ACTIVE);
    }

    public function scopeActive($query)
    {
        return $query->where(self::IS_ACTIVE, true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(self::USER_ID, $userId);
    }

    public function scopeForDeviceType($query, string $deviceType)
    {
        return $query->where(self::DEVICE_TYPE, $deviceType);
    }
}