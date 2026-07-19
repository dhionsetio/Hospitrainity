<?php

namespace App\Services\Curriculum;

use JsonException;

final class CanonicalJson
{
    /**
     * Encode JSON with recursively sorted object keys and preserved list order.
     * The curriculum contains no floating-point values, so this intentionally
     * avoids claiming full RFC 8785/JCS number serialization compatibility.
     *
     * @throws JsonException
     */
    public static function encode(mixed $value, bool $pretty = false): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode(self::sort($value), $flags);
    }

    private static function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::sort(...), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = self::sort($item);
        }

        return $value;
    }
}
