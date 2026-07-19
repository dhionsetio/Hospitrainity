<?php

namespace App\Rules;

use App\Services\PublicMediaManager;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Storage;

class PublicAudioUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_starts_with($value, '/storage/')) {
            $fail('The :attribute field must reference an existing public MP3 or WAV file.');

            return;
        }

        $path = PublicMediaManager::pathFromUrl($value);
        if (
            $path === null
            || preg_match('/\.(?:mp3|wav)$/iD', $path) !== 1
            || ! Storage::disk('public')->exists($path)
        ) {
            $fail('The :attribute field must reference an existing public MP3 or WAV file.');
        }
    }
}
