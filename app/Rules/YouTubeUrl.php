<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YouTubeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::videoId($value) === null) {
            $fail('The :attribute field must be a valid HTTPS YouTube video URL.');
        }
    }

    public static function canonicalize(string $url): ?string
    {
        $videoId = self::videoId($url);

        return $videoId === null
            ? null
            : "https://www.youtube.com/embed/{$videoId}";
    }

    public static function videoId(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        $path = trim($parts['path'] ?? '', '/');
        $candidate = null;

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $candidate = explode('/', $path)[0] ?? null;
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ($path === 'watch') {
                parse_str($parts['query'] ?? '', $query);
                $candidate = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (str_starts_with($path, 'embed/') || str_starts_with($path, 'shorts/')) {
                $candidate = explode('/', $path)[1] ?? null;
            }
        }

        return is_string($candidate) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $candidate) === 1
            ? $candidate
            : null;
    }
}
