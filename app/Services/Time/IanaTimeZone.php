<?php

namespace App\Services\Time;

use DateTimeZone;
use InvalidArgumentException;

final class IanaTimeZone
{
    /** @var array<string, true>|null */
    private static ?array $identifiers = null;

    public static function nullable(mixed $identifier): ?string
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        if (! is_string($identifier)) {
            throw new InvalidArgumentException('Timezone must be a current IANA timezone identifier.');
        }

        return self::required($identifier);
    }

    public static function required(string $identifier): string
    {
        if ($identifier !== trim($identifier) || ! isset(self::identifiers()[$identifier])) {
            throw new InvalidArgumentException('Timezone must be a current IANA timezone identifier.');
        }

        return $identifier;
    }

    /** @return array<string, true> */
    private static function identifiers(): array
    {
        if (self::$identifiers === null) {
            self::$identifiers = array_fill_keys(DateTimeZone::listIdentifiers(DateTimeZone::ALL), true);
            self::$identifiers['UTC'] = true;
        }

        return self::$identifiers;
    }
}
