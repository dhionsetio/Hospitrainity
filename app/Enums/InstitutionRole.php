<?php

namespace App\Enums;

enum InstitutionRole: string
{
    case Learner = 'learner';
    case Instructor = 'instructor';
    case InstitutionAdmin = 'institution_admin';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
