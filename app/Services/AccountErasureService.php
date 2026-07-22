<?php

namespace App\Services;

use App\Enums\AccountDisableReason;
use App\Enums\DataSubjectRequestStatus;
use App\Models\AccountErasureStep;
use App\Models\DataSubjectRequest;
use App\Models\IdentityAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AccountErasureService
{
    /** @var list<string> */
    private const STEPS = [
        'revoke_access',
        'stop_notifications',
        'remove_personal_learning',
        'close_memberships',
        'pseudonymize_identity',
    ];

    public function execute(DataSubjectRequest $request, DataSubjectRequestService $requests): void
    {
        if ($request->status !== DataSubjectRequestStatus::Executing || $request->user_id === null) {
            return;
        }

        foreach (self::STEPS as $step) {
            $record = AccountErasureStep::query()->firstOrCreate(
                ['data_subject_request_id' => $request->getKey(), 'step' => $step],
                ['status' => 'pending'],
            );
            if ($record->status === 'completed') {
                continue;
            }

            $record->forceFill([
                'status' => 'executing',
                'attempts' => $record->attempts + 1,
                'started_at' => now(),
                'last_error_code' => null,
            ])->save();

            try {
                DB::transaction(fn () => $this->{$step}($request));
                $record->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
            } catch (Throwable $exception) {
                report($exception);
                $record->forceFill(['status' => 'failed', 'last_error_code' => 'step_failed'])->save();
                throw new RuntimeException('account_erasure_step_failed:'.$step, previous: $exception);
            }
        }

        $actor = $request->assignee;
        if ($actor === null) {
            throw new RuntimeException('account_erasure_missing_approver');
        }
        $requests->transition($request->fresh(), DataSubjectRequestStatus::Completed, $actor, null, null, false);
    }

    private function revoke_access(DataSubjectRequest $request): void
    {
        $user = User::query()->whereKey($request->user_id)->lockForUpdate()->firstOrFail();
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $user->forceFill([
            'disabled_at' => $user->disabled_at ?? now(),
            'disabled_by_user_id' => $request->assigned_to_user_id,
            'disabled_reason_code' => AccountDisableReason::PrivacyRequest,
            'remember_token' => Str::random(60),
        ])->save();
    }

    private function stop_notifications(DataSubjectRequest $request): void
    {
        DB::table('push_subscriptions')
            ->where('user_id', $request->user_id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    private function remove_personal_learning(DataSubjectRequest $request): void
    {
        DB::table('completions')
            ->where('user_id', $request->user_id)
            ->whereNull('institution_membership_id')
            ->delete();

        $attemptIds = DB::table('curriculum_attempts')
            ->where('user_id', $request->user_id)
            ->whereNull('institution_membership_id')
            ->pluck('id');
        if ($attemptIds->isNotEmpty()) {
            DB::table('curriculum_responses')->whereIn('curriculum_attempt_id', $attemptIds)->delete();
            DB::table('curriculum_attempt_events')->whereIn('curriculum_attempt_id', $attemptIds)->delete();
            DB::table('curriculum_attempts')->whereIn('id', $attemptIds)->delete();
        }
        DB::table('learner_text_responses')
            ->where('user_id', $request->user_id)
            ->whereNull('institution_membership_id')
            ->delete();
        DB::table('curriculum_activity_progress')
            ->where('user_id', $request->user_id)
            ->whereNull('institution_membership_id')
            ->delete();
    }

    private function close_memberships(DataSubjectRequest $request): void
    {
        $membershipIds = DB::table('institution_memberships')
            ->where('user_id', $request->user_id)
            ->pluck('id');
        if ($membershipIds->isNotEmpty()) {
            DB::table('institution_role_assignments')->whereIn('institution_membership_id', $membershipIds)
                ->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
            DB::table('institution_memberships')->whereIn('id', $membershipIds)
                ->whereNull('revoked_at')->update([
                    'status' => 'revoked',
                    'is_default' => false,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
        }
        DB::table('platform_role_assignments')->where('user_id', $request->user_id)
            ->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        DB::table('user_capability_assignments')->where('user_id', $request->user_id)
            ->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        DB::table('institution_join_codes')->where('issued_by_user_id', $request->user_id)
            ->whereNull('revoked_at')->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $request->assigned_to_user_id,
                'updated_at' => now(),
            ]);
        DB::table('institution_invitations')->where('issued_by_user_id', $request->user_id)
            ->whereNull('revoked_at')->whereNull('accepted_at')->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $request->assigned_to_user_id,
                'updated_at' => now(),
            ]);
    }

    private function pseudonymize_identity(DataSubjectRequest $request): void
    {
        $user = User::query()->whereKey($request->user_id)->lockForUpdate()->firstOrFail();
        $reference = substr($request->subject_reference_hash, 0, 24);
        $user->forceFill([
            'name' => 'Deleted user '.$reference,
            'email' => 'deleted+'.$reference.'@invalid.example',
            'instansi' => '',
            'email_verified_at' => null,
            'password' => Str::random(72),
            'remember_token' => Str::random(60),
        ])->save();

        IdentityAudit::query()->create([
            'actor_user_id' => $request->assigned_to_user_id,
            'target_user_id' => $user->getKey(),
            'event' => 'privacy.account_pseudonymized',
            'metadata' => ['request_id' => $request->getKey(), 'retained_scope' => 'institution_evidence_only'],
            'created_at' => now(),
        ]);
    }
}
