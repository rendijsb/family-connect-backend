<?php

declare(strict_types=1);

namespace App\Enums\Memories;

enum MemoryTypeEnum: string
{
    case GENERAL = 'general';
    case MILESTONE = 'milestone';
    case ACHIEVEMENT = 'achievement';
    case TRADITION = 'tradition';
    case STORY = 'story';
    case VACATION = 'vacation';
    case HOLIDAY = 'holiday';
    case BIRTHDAY = 'birthday';
    case ANNIVERSARY = 'anniversary';
    case FIRST_TIME = 'first_time';
    case FUNNY_MOMENT = 'funny_moment';
    case LIFE_LESSON = 'life_lesson';
}