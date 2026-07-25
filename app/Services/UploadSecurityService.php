<?php

namespace App\Services;

use App\Models\UploadSecurityRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class UploadSecurityService
{
    /** @param array{original_name: string, sha256: string, bytes: int, mime: string} $metadata */
    public function inspect(UploadedFile $file, User $actor, string $kind, array $metadata): UploadSecurityRecord
    {
        $driver = (string) config('upload_security.driver', 'clamav');
        [$status, $code] = $this->scan($file, $driver);
        $record = UploadSecurityRecord::query()->create([
            'actor_user_id' => $actor->getKey(),
            'kind' => mb_substr($kind, 0, 32),
            'original_name' => basename($metadata['original_name']),
            'sha256' => $metadata['sha256'],
            'bytes' => $metadata['bytes'],
            'detected_mime' => $metadata['mime'],
            'scanner_driver' => mb_substr($driver, 0, 32),
            'status' => $status,
            'result_code' => $code,
        ]);

        if ($status === 'malicious') {
            throw ValidationException::withMessages([$kind === 'asset' ? 'asset' : 'source' => __('The upload was rejected by the malware scanner.')]);
        }
        if ($status !== 'clean' && (bool) config('upload_security.required')) {
            throw ValidationException::withMessages([$kind === 'asset' ? 'asset' : 'source' => __('Uploads are temporarily unavailable because the required security scanner is not healthy.')]);
        }

        return $record;
    }

    public function markPromoted(UploadSecurityRecord $record): void
    {
        $record->forceFill(['promoted_at' => now()])->save();
    }

    public function healthy(): bool
    {
        [$status] = $this->scanPath(null, (string) config('upload_security.driver', 'clamav'), true);

        return $status === 'clean';
    }

    /** @return array{string, string|null} */
    private function scan(UploadedFile $file, string $driver): array
    {
        return $this->scanPath($file->getRealPath() ?: null, $driver, false);
    }

    /** @return array{string, string|null} */
    private function scanPath(?string $path, string $driver, bool $health): array
    {
        if ($driver !== 'clamav') {
            return ['unavailable', 'unsupported_driver'];
        }
        $binary = trim((string) config('upload_security.clamav.binary'));
        if ($binary === '') {
            return ['unavailable', 'binary_not_configured'];
        }
        $executable = is_file($binary) ? $binary : (new ExecutableFinder)->find($binary);
        if ($executable === null) {
            return ['unavailable', 'binary_not_found'];
        }

        try {
            $command = $health ? [$executable, '--version'] : [$executable, '--no-summary', '--infected', (string) $path];
            $result = Process::timeout((int) config('upload_security.clamav.timeout_seconds', 30))->run($command);
        } catch (Throwable) {
            return ['unavailable', 'process_failed'];
        }

        if ($health) {
            return $result->successful() ? ['clean', 'version_ok'] : ['unavailable', 'health_failed'];
        }

        return match ($result->exitCode()) {
            0 => ['clean', 'clamav_clean'],
            1 => ['malicious', 'clamav_detected'],
            default => ['unavailable', 'clamav_error'],
        };
    }
}
