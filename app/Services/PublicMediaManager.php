<?php

namespace App\Services;

use App\Models\PendingMediaDeletion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PublicMediaManager
{
    private const DISK = 'public';

    /** @var array<string, true> */
    private array $stagedPaths = [];

    /** @var array<string, true> */
    private array $retiredPaths = [];

    /**
     * Store a new upload before its database transaction. If that transaction
     * fails, rollbackStaged() removes it; otherwise finalize() retains it.
     */
    public function stage(UploadedFile $file, string $directory): string
    {
        $path = Storage::disk(self::DISK)->putFile(trim($directory, '/'), $file);
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The media file could not be stored.');
        }

        $path = str_replace('\\', '/', $path);
        $this->stagedPaths[$path] = true;

        return self::urlForPath($path);
    }

    /** Queue a database-owned local URL for deletion after commit. */
    public function retire(?string $url): void
    {
        $path = self::pathFromUrl($url);
        if ($path !== null) {
            $this->retiredPaths[$path] = true;
        }
    }

    /**
     * Persist cleanup work inside the same DB transaction as the content
     * mutation. Failed after-commit deletions therefore remain visible and
     * retryable instead of becoming silent filesystem orphans.
     */
    public function queueRetirements(): void
    {
        foreach (array_keys($this->retiredPaths) as $path) {
            PendingMediaDeletion::query()->firstOrCreate([
                'disk' => self::DISK,
                'path' => $path,
            ]);
        }
    }

    /** Delete staged uploads when their database mutation rolls back. */
    public function rollbackStaged(): void
    {
        foreach (array_keys($this->stagedPaths) as $path) {
            try {
                Storage::disk(self::DISK)->delete($path);
            } catch (Throwable $exception) {
                Log::error('Unable to remove a rolled-back staged media file.', [
                    'disk' => self::DISK,
                    'path' => $path,
                    'exception' => $exception,
                ]);
            }
        }

        $this->stagedPaths = [];
        $this->retiredPaths = [];
    }

    /**
     * Mark staged files as committed, then process the durable cleanup queue.
     * Cleanup failures are recorded for retry and never remove newly referenced
     * files or roll back an already committed database change.
     */
    public function finalize(): void
    {
        $this->stagedPaths = [];
        $paths = array_keys($this->retiredPaths);
        $this->retiredPaths = [];

        $this->processPending(paths: $paths);
    }

    /** @return array{processed: int, remaining: int} */
    public function processPending(int $limit = 100, ?array $paths = null): array
    {
        $query = PendingMediaDeletion::query()->orderBy('id')->limit(max(1, min($limit, 1000)));
        if ($paths !== null) {
            if ($paths === []) {
                return ['processed' => 0, 'remaining' => PendingMediaDeletion::query()->count()];
            }
            $query->where('disk', self::DISK)->whereIn('path', $paths);
        }

        $processed = 0;
        foreach ($query->get() as $pending) {
            try {
                $disk = Storage::disk($pending->disk);
                $deleted = ! $disk->exists($pending->path) || $disk->delete($pending->path);

                if (! $deleted) {
                    throw new RuntimeException('The filesystem returned false while deleting the file.');
                }

                $pending->delete();
                $processed++;
            } catch (Throwable $exception) {
                $pending->forceFill([
                    'attempts' => $pending->attempts + 1,
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                ])->save();

                Log::warning('Public media cleanup remains pending.', [
                    'disk' => $pending->disk,
                    'path' => $pending->path,
                    'exception' => $exception,
                ]);
            }
        }

        return [
            'processed' => $processed,
            'remaining' => PendingMediaDeletion::query()->count(),
        ];
    }

    public static function urlForPath(string $path): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }

    public static function pathFromUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $allowedHosts = array_values(array_filter([
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            strtolower((string) parse_url((string) config('filesystems.disks.public.url'), PHP_URL_HOST)),
        ]));
        if ($host !== '' && ! in_array($host, $allowedHosts, true)) {
            return null;
        }

        $path = $parts['path'] ?? null;
        if (! is_string($path)) {
            return null;
        }

        // Decode first so encoded Windows separators cannot bypass traversal
        // checks, then normalize every platform to forward slashes.
        $path = str_replace('\\', '/', rawurldecode($path));
        if (! str_starts_with($path, '/storage/')) {
            return null;
        }

        $relative = ltrim(substr($path, strlen('/storage/')), '/');
        if ($relative === '' || str_contains($relative, "\0") || preg_match('#(^|/)\.\.(/|$)#', $relative)) {
            return null;
        }

        return $relative;
    }
}
