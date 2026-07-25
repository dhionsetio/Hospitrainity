<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Normalizer;

class SecurePassword implements ValidationRule
{
    /** @param list<string> $context */
    public function __construct(private readonly array $context = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        $normalized = class_exists(Normalizer::class)
            ? (Normalizer::normalize($value, Normalizer::FORM_C) ?: $value)
            : $value;
        $length = mb_strlen($normalized);
        $minimum = (int) config('authentication.password.minimum', 8);
        $maximum = (int) config('authentication.password.maximum', 128);
        if ($length < $minimum) {
            $fail("The :attribute must be at least {$minimum} characters.");

            return;
        }
        if ($length > $maximum) {
            $fail("The :attribute must not exceed {$maximum} characters.");

            return;
        }

        $candidate = mb_strtolower($normalized);
        if ($this->isCommon($candidate)) {
            $fail('This password appears in the local common-password blocklist. Choose a less predictable password.');

            return;
        }

        $context = ['hospitrainity', ...$this->context];
        foreach ($context as $term) {
            foreach (preg_split('/[^\pL\pN]+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                if (mb_strlen($word) >= 4 && str_contains($candidate, $word)) {
                    $fail('The password must not contain the application name or easily guessed account details.');

                    return;
                }
            }
        }
    }

    private function isCommon(string $candidate): bool
    {
        $path = resource_path('security/common-passwords.txt');
        if (! is_file($path)) {
            return true;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return true;
        }
        try {
            while (($line = fgets($handle)) !== false) {
                if (hash_equals(rtrim(mb_strtolower($line), "\r\n"), $candidate)) {
                    return true;
                }
            }
        } finally {
            fclose($handle);
        }

        return false;
    }
}
