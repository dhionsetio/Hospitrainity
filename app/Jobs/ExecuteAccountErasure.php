<?php

namespace App\Jobs;

use App\Enums\DataSubjectRequestStatus;
use App\Models\DataSubjectRequest;
use App\Services\AccountErasureService;
use App\Services\DataSubjectRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteAccountErasure implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly string $requestId) {}

    public function handle(AccountErasureService $eraser, DataSubjectRequestService $requests): void
    {
        $request = DataSubjectRequest::query()->with(['user', 'assignee'])->findOrFail($this->requestId);
        if ($request->status === DataSubjectRequestStatus::Approved) {
            $actor = $request->assignee;
            if ($actor === null) {
                return;
            }
            $request = $requests->transition($request, DataSubjectRequestStatus::Executing, $actor, null, null, false)
                ->load(['user', 'assignee']);
        }
        if ($request->status !== DataSubjectRequestStatus::Executing || $request->user === null) {
            return;
        }

        try {
            $eraser->execute($request, $requests);
        } catch (Throwable $exception) {
            $fresh = $request->fresh(['assignee']);
            if ($fresh?->status === DataSubjectRequestStatus::Executing && $fresh->assignee !== null) {
                $requests->transition($fresh, DataSubjectRequestStatus::Failed, $fresh->assignee, 'erasure_step_failed', null, false);
            }
            throw $exception;
        }
    }
}
