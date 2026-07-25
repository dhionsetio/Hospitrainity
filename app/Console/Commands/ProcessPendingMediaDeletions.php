<?php

namespace App\Console\Commands;

use App\Models\PendingMediaDeletion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessPendingMediaDeletions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'learning:process-pending-media-deletions {--limit=100 : Maximum number of files to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending media file deletions from the durable queue for private learner media';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $privateDiskName = (string) config('learning_reflection.disk', 'learner_media_private');

        $pendingDeletions = PendingMediaDeletion::query()
            ->where('disk', $privateDiskName)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processedCount = 0;

        foreach ($pendingDeletions as $pending) {
            try {
                $disk = Storage::disk($pending->disk);
                if (! $disk->exists($pending->path) || $disk->delete($pending->path)) {
                    $pending->delete();
                    $processedCount++;
                }
            } catch (Throwable $exception) {
                $pending->forceFill([
                    'attempts' => $pending->attempts + 1,
                    'last_error' => Str::limit($exception->getMessage(), 1000),
                ])->save();
            }
        }

        $this->info("Processed {$processedCount} pending private media deletions.");
        Log::info("learning:process-pending-media-deletions completed.", ['count' => $processedCount]);

        return Command::SUCCESS;
    }
}
