<?php

declare(strict_types=1);

namespace App\Enums\Memories;

enum MilestoneTypeEnum: string
{
    case BIRTH = 'birth';
    case FIRST_STEPS = 'first_steps';
    case FIRST_WORDS = 'first_words';
    case FIRST_DAY_SCHOOL = 'first_day_school';
    case GRADUATION = 'graduation';
    case FIRST_JOB = 'first_job';
    case ENGAGEMENT = 'engagement';
    case WEDDING = 'wedding';
    case NEW_HOME = 'new_home';
    case RETIREMENT = 'retirement';
    case ACHIEVEMENT = 'achievement';
    case AWARD = 'award';
}