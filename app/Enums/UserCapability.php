<?php

namespace App\Enums;

enum UserCapability: string
{
    case ContentAuthor = 'content_author';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $capability): string => $capability->value, self::cases());
    }
}
