<?php

namespace App\Enums;

enum TeachingAssignmentRole: string
{
    case Primary = 'primary';
    case CoInstructor = 'co_instructor';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
