<?php

declare(strict_types=1);

namespace App\Enums\Memories;

enum MemoryVisibilityEnum: string
{
    case FAMILY = 'family';
    case SPECIFIC_MEMBERS = 'specific_members';
    case PRIVATE = 'private';
    case PUBLIC = 'public';
}