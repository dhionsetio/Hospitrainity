<?php

namespace App\Enums;

enum PlatformRole: string
{
    case SystemAdmin = 'system_admin';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
