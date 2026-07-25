<?php

namespace App\Services;

use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectRequestType;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Jobs\ExecuteAccountErasure;
use App\Jobs\GenerateDataExport;
use App\Jobs\SendPushNotification;
use App\Models\DataSubjectRequest;
use App\Models\DataSubjectRequestEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataSubjectRequestService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'submitted' => ['in_review', 'cancelled'],
        'identity_pending' => ['in_review', 'cancelled'],
        'in_review' => ['approved', 'denied', 'held'],
        'approved' => ['executing', 'completed', 'held'],
        'held' => ['in_review', 'approved', 'denied'],
        'executing' => ['completed', 'failed'],
        'failed' => ['executing', 'held'],
        'denied' => ['appealed'],
        'appealed' => ['in_review', 'held'],
    ];

    public function submit(User $user, DataSubjectRequestType $type, ?string $note, bool $recentPassword): DataSubjectRequest
    {
        $sensitive = in_array($type, [DataSubjectRequestType::AccessExport, DataSubjectRequestType::Deletion], true);
        if ($sensitive && ! $recentPassword) {
            throw new DomainException('recent_password_required');
        }

        return DB::transaction(function () use ($user, $type, $note): DataSubjectRequest {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $duplicate = DataSubjectRequest::query()
                ->where('user_id', $user->getKey())
                ->where('type', $type->value)
                ->whereNotIn('status', [
                    DataSubjectRequestStatus::Denied->value,
                    DataSubjectRequestStatus::Completed->value,
                    DataSubjectRequestStatus::Cancelled->value,
                ])
                ->first();
            if ($duplicate !== null) {
                throw new DomainException('duplicate_active_request');
            }

            $request = DataSubjectRequest::query()->create([
                'user_id' => $user->getKey(),
                'subject_reference_hash' => $this->subjectReference($user),
                'type' => $type,
                'status' => DataSubjectRequestStatus::Submitted,
                'request_note' => Str::limit(trim((string) $note), 2000, ''),
                'identity_verified_at' => now(),
                'due_at' => now()->addDays((int) config('privacy.request_due_days')),
            ]);
            $this->event($request, $user, 'request.submitted', ['type' => $type->value]);

            return $request;
        }, attempts: 3);
    }

    public function cancel(DataSubjectRequest $request, User $actor): DataSubjectRequest
    {
        if ((int) $request->user_id !== (int) $actor->getKey()) {
            throw new DomainException('request_not_owned');
        }

        return $this->transition($request, DataSubjectRequestStatus::Cancelled, $actor, null, null);
    }

    public function transition(
        DataSubjectRequest $request,
        DataSubjectRequestStatus $to,
        User $actor,
        ?string $reasonCode,
        ?string $decisionNote,
        bool $dispatchJobs = true,
    ): DataSubjectRequest {
        $request = DB::transaction(function () use ($request, $to, $actor, $reasonCode, $decisionNote): DataSubjectRequest {
            $locked = DataSubjectRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            $allowed = self::TRANSITIONS[$from->value] ?? [];
            if (! in_array($to->value, $allowed, true)) {
                throw new DomainException('invalid_privacy_request_transition');
            }
            if ($to === DataSubjectRequestStatus::Approved
                && $locked->type === DataSubjectRequestType::Deletion
                && (int) $locked->user_id === (int) $actor->getKey()) {
                throw new DomainException('deletion_self_approval_denied');
            }
            if ($to === DataSubjectRequestStatus::Approved
                && $locked->type === DataSubjectRequestType::Deletion
                && User::query()->find($locked->user_id)?->isSuperAdmin()
                && $this->activeSystemAdminCount() <= 1) {
                throw new DomainException('last_system_admin_deletion_denied');
            }

            $locked->forceFill([
                'status' => $to,
                'assigned_to_user_id' => $actor->getKey(),
                'reason_code' => $reasonCode === null ? $locked->reason_code : Str::limit($reasonCode, 80, ''),
                'decision_note' => $decisionNote === null ? $locked->decision_note : Str::limit(trim($decisionNote), 2000, ''),
                'decided_at' => in_array($to, [DataSubjectRequestStatus::Approved, DataSubjectRequestStatus::Denied], true) ? now() : $locked->decided_at,
                'executing_at' => $to === DataSubjectRequestStatus::Executing ? now() : $locked->executing_at,
                'completed_at' => $to === DataSubjectRequestStatus::Completed ? now() : $locked->completed_at,
                'cancelled_at' => $to === DataSubjectRequestStatus::Cancelled ? now() : $locked->cancelled_at,
            ])->save();

            $this->event($locked, $actor, 'request.status_changed', [
                'from' => $from->value,
                'to' => $to->value,
                'reason_code' => $locked->reason_code,
            ]);

            return $locked->fresh(['export', 'events']);
        }, attempts: 3);

        if ($dispatchJobs && in_array($to, [DataSubjectRequestStatus::Approved, DataSubjectRequestStatus::Executing], true)) {
            if ($request->type === DataSubjectRequestType::AccessExport) {
                GenerateDataExport::dispatch($request->getKey());
            } elseif ($request->type === DataSubjectRequestType::Deletion) {
                ExecuteAccountErasure::dispatch($request->getKey());
            }
        }

        if ($request->user_id !== null) {
            SendPushNotification::dispatch(
                (int) $request->user_id,
                'Hospitrainity privacy request update',
                'Your request status is now '.str_replace('_', ' ', $request->status->value).'.',
                '/privacy/requests',
            );
        }

        return $request;
    }

    /** @param array<string, scalar|null> $metadata */
    public function event(DataSubjectRequest $request, ?User $actor, string $event, array $metadata = []): void
    {
        DataSubjectRequestEvent::query()->create([
            'data_subject_request_id' => $request->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'event' => Str::limit($event, 80, ''),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function subjectReference(User $user): string
    {
        return hash_hmac('sha256', (string) $user->getKey().'|'.$user->email, (string) config('app.key'));
    }

    private function activeSystemAdminCount(): int
    {
        return User::query()
            ->whereNull('disabled_at')
            ->where(function ($query): void {
                $query->where('role', UserRole::Superadmin->value)
                    ->orWhereHas('platformRoleAssignments', fn ($assignments) => $assignments
                        ->where('role', PlatformRole::SystemAdmin->value)
                        ->whereNull('revoked_at'));
            })
            ->distinct()
            ->count('users.id');
    }
}
