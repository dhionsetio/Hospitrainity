<?php

namespace App\Enums;

enum CurriculumReleaseState: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Active = 'active';
    case Retired = 'retired';
    case Withdrawn = 'withdrawn';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $state): string => $state->value, self::cases());
    }
}
