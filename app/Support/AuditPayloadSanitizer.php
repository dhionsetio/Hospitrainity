<?php

namespace App\Support;

use Illuminate\Support\Str;

final class AuditPayloadSanitizer
{
    /**
     * Keys that must never be persisted in administration or curriculum audit metadata.
     * Safe aggregate fields such as `sessions_revoked` are deliberately not blocked.
     *
     * @var list<string>
     */
    private const BLOCKED_KEYS = [
        'access_token',
        'answer',
        'answers',
        'audio',
        'audio_blob',
        'authorization',
        'client_secret',
        'compiled_package_path',
        'cookie',
        'cookies',
        'current_password',
        'evidence_path',
        'file_contents',
        'password',
        'password_confirmation',
        'raw_response',
        'refresh_token',
        'response',
        'response_payload',
        'responses',
        'secret',
        'session_id',
        'session_ids',
        'source_storage_path',
        'token',
    ];

    private const MAX_DEPTH = 4;

    private const MAX_ITEMS = 50;

    public static function metadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $clean = self::array($metadata, 0);

        return $clean === [] ? null : $clean;
    }

    public static function text(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);

        return $value === '' ? null : Str::limit($value, $limit, '');
    }

    public static function singleLine(?string $value, int $limit): ?string
    {
        $value = self::text($value, $limit);
        if ($value === null) {
            return null;
        }

        return Str::limit(preg_replace('/\s+/u', ' ', $value) ?? '', $limit, '');
    }

    /** @param array<array-key, mixed> $values @return array<array-key, mixed> */
    private static function array(array $values, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['truncated' => true];
        }

        $clean = [];
        foreach (array_slice($values, 0, self::MAX_ITEMS, true) as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower(trim($key)) : $key;
            if (is_string($normalizedKey) && in_array($normalizedKey, self::BLOCKED_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::array($value, $depth + 1);
            } elseif (is_string($value)) {
                $clean[$key] = self::singleLine($value, 500);
            } elseif (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        if (count($values) > self::MAX_ITEMS) {
            $clean['truncated'] = true;
        }

        return $clean;
    }
}
