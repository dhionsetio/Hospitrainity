<?php

namespace App\Enums;

enum CurriculumDraftEntityType: string
{
    case Chapter = 'chapter';
    case Section = 'lesson-section';
    case Outcome = 'outcome';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
