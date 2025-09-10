<?php

declare(strict_types=1);

namespace App\Enums\Memories;

enum TraditionFrequencyEnum: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
    case SPECIAL = 'special';
    case SEASONAL = 'seasonal';
    case HOLIDAY = 'holiday';
}