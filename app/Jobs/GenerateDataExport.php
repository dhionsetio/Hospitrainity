<?php

namespace App\Jobs;

use App\Enums\DataSubjectRequestStatus;
use App\Models\DataExport;
use App\Models\DataSubjectRequest;
use App\Services\DataExportBuilder;
use App\Services\DataSubjectRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateDataExport implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly string $requestId) {}

    public function handle(DataExportBuilder $builder, DataSubjectRequestService $requests): void
    {
        $request = DataSubjectRequest::query()->with(['user', 'assignee'])->findOrFail($this->requestId);
        $user = $request->user;
        $actor = $request->assignee;
        if ($user === null || $actor === null) {
            return;
        }
        if ($request->status === DataSubjectRequestStatus::Approved) {
            $request = $requests->transition($request, DataSubjectRequestStatus::Executing, $actor, null, null, false);
        }
        if ($request->status !== DataSubjectRequestStatus::Executing) {
            return;
        }

        $export = DataExport::query()->firstOrCreate(
            ['data_subject_request_id' => $request->getKey()],
            ['user_id' => $user->getKey(), 'status' => 'processing'],
        );
        if ($export->status === 'available' && $export->expires_at?->isFuture()) {
            return;
        }

        try {
            $payload = $builder->build($user);
            $path = 'privacy-exports/'.$export->getKey().'.zip.enc';
            Storage::disk('local')->put($path, Crypt::encryptString($payload['bytes']));

            DB::transaction(function () use ($actor, $export, $payload, $path, $request, $requests): void {
                $export->forceFill([
                    'status' => 'available',
                    'encrypted_path' => $path,
                    'payload_sha256' => $payload['sha256'],
                    'payload_bytes' => $payload['size'],
                    'available_at' => now(),
                    'expires_at' => now()->addHours((int) config('privacy.export_expiry_hours')),
                    'failure_code' => null,
                ])->save();
                $requests->transition($request, DataSubjectRequestStatus::Completed, $actor, null, null, false);
            });
        } catch (Throwable $exception) {
            report($exception);
            $export->forceFill(['status' => 'failed', 'failure_code' => 'generation_failed'])->save();
            if ($request->status === DataSubjectRequestStatus::Executing) {
                $requests->transition($request, DataSubjectRequestStatus::Failed, $actor, 'generation_failed', null, false);
            }
            throw $exception;
        }
    }
}
