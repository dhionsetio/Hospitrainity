<?php

namespace App\Enums;

enum LegacyInstitutionState: string
{
    case Mapped = 'mapped';
    case Unresolved = 'unresolved';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $state): string => $state->value, self::cases());
    }
}
