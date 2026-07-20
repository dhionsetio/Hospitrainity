<?php

use App\Services\PrivacyRetentionService;
use App\Services\PublicMediaManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hospitrainity:media-cleanup {--limit=100}', function () {
    $result = app(PublicMediaManager::class)->processPending((int) $this->option('limit'));
    $this->info("Processed {$result['processed']} pending media deletion(s); {$result['remaining']} remain.");

    return $result['remaining'] === 0 ? Command::SUCCESS : Command::FAILURE;
})->purpose('Retry durable public-media cleanup jobs left by committed content changes')
    ->hourly()
    ->withoutOverlapping();

Artisan::command('hospitrainity:storage-health', function () {
    if (! is_dir(public_path('storage'))) {
        $this->error('public/storage is missing. Run: php artisan storage:link');

        return Command::FAILURE;
    }

    $path = '.health/'.Str::uuid().'.txt';
    $disk = Storage::disk('public');

    try {
        $written = $disk->put($path, 'hospitrainity-storage-health');
        if (! $written || ! $disk->exists($path)) {
            $this->error('The public disk write/read probe failed.');

            return Command::FAILURE;
        }

        if (! is_file(public_path('storage/'.$path))) {
            $this->error('The public/storage link does not resolve the stored probe file.');

            return Command::FAILURE;
        }

        $url = PublicMediaManager::urlForPath($path);
        if (! str_starts_with($url, '/storage/')) {
            $this->error('The public media URL contract is invalid.');

            return Command::FAILURE;
        }

        $this->info("Public storage is writable and web-mapped at {$url}.");

        return Command::SUCCESS;
    } catch (Throwable $exception) {
        $this->error('Public storage health check failed: '.$exception->getMessage());

        return Command::FAILURE;
    } finally {
        try {
            $disk->delete($path);
        } catch (Throwable) {
            // The command already reports the primary failure; cleanup can be
            // retried manually if this best-effort probe deletion also fails.
        }
    }
})->purpose('Verify the public storage link and a write/read/delete media probe');

Artisan::command('hospitrainity:privacy-retention {--execute}', function () {
    $result = app(PrivacyRetentionService::class)->run((bool) $this->option('execute'));
    $mode = $result['execute'] ? 'executed' : 'dry-run';
    $this->info("Privacy retention {$mode}: {$result['expired_exports']} expired export(s), {$result['minimized_requests']} request(s) ready for minimization, {$result['removed_subscriptions']} revoked subscription(s).");

    return Command::SUCCESS;
})->purpose('Preview or execute approved privacy retention and artifact expiry');

Schedule::command('hospitrainity:privacy-retention --execute')
    ->dailyAt('02:30')
    ->withoutOverlapping();
