<?php

declare(strict_types=1);

namespace App\Enums\Photos;

enum AlbumPrivacyEnum: string
{
    case FAMILY = 'family';
    case SPECIFIC_MEMBERS = 'specific_members';
    case PUBLIC = 'public';
    case PRIVATE = 'private';
}