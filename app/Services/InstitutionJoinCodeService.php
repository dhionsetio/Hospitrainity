<?php

namespace App\Services;

use App\Enums\InstitutionJoinRequestStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Exceptions\JoinCodeUnavailableException;
use App\Models\IdentityAudit;
use App\Models\Institution;
use App\Models\InstitutionJoinCode;
use App\Models\InstitutionJoinRequest;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class InstitutionJoinCodeService
{
    public const MIN_TTL_SECONDS = 1;

    public const MAX_TTL_SECONDS = 30 * 24 * 60 * 60;

    public const DEFAULT_TTL_SECONDS = 60 * 60;

    private const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const CODE_LENGTH = 16;

    public function __construct(private readonly InstitutionAccessService $access) {}

    /** @return array{record: InstitutionJoinCode, code: string} */
    public function issue(
        User $actor,
        Institution $institution,
        int $useLimit = 100,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): array {
        $useLimit = max(1, min(500, $useLimit));
        if ($ttlSeconds < self::MIN_TTL_SECONDS || $ttlSeconds > self::MAX_TTL_SECONDS) {
            throw new InvalidArgumentException('Join-code duration must be between one second and 30 days.');
        }
        $plain = $this->generateCode();

        $record = DB::transaction(function () use ($actor, $institution, $useLimit, $ttlSeconds, $plain): InstitutionJoinCode {
            $lockedInstitution = Institution::query()->lockForUpdate()->find($institution->getKey());
            $lockedActor = User::query()->lockForUpdate()->find($actor->getKey());
            if ($lockedInstitution === null
                || $lockedInstitution->status !== InstitutionStatus::Active
                || $lockedActor === null
                || $lockedActor->isDisabled()) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            $this->access->authorizeLearnerManagement($lockedActor, $lockedInstitution);

            $record = InstitutionJoinCode::query()->create([
                'institution_id' => $lockedInstitution->getKey(),
                'issued_by_user_id' => $lockedActor->getKey(),
                'token_hash' => $this->hash($plain),
                'display_suffix' => substr($plain, -4),
                'expires_at' => now()->addSeconds($ttlSeconds),
                'use_limit' => $useLimit,
                'use_count' => 0,
            ]);
            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'institution_id' => $lockedInstitution->getKey(),
                'event' => 'join_code.issued',
                'metadata' => [
                    'join_code_id' => $record->getKey(),
                    'expires_at' => $record->expires_at->toAtomString(),
                    'ttl_seconds' => $ttlSeconds,
                    'use_limit' => $useLimit,
                ],
                'created_at' => now(),
            ]);

            return $record;
        }, attempts: 3);

        return ['record' => $record, 'code' => $this->format($plain)];
    }

    public function revoke(User $actor, InstitutionJoinCode $code): void
    {
        DB::transaction(function () use ($actor, $code): void {
            $reference = InstitutionJoinCode::query()->select(['id', 'institution_id'])->find($code->getKey());
            if ($reference === null) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            $institution = Institution::query()->lockForUpdate()->find($reference->institution_id);
            $lockedActor = User::query()->lockForUpdate()->find($actor->getKey());
            $lockedCode = InstitutionJoinCode::query()->whereKey($reference->getKey())->lockForUpdate()->first();
            if ($institution === null || $lockedActor === null || $lockedCode === null) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            $this->access->authorizeLearnerManagement($lockedActor, $institution);
            if ($lockedCode->revoked_at !== null) {
                return;
            }
            $lockedCode->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $lockedActor->getKey(),
            ])->save();
            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'institution_id' => $institution->getKey(),
                'event' => 'join_code.revoked',
                'metadata' => ['join_code_id' => $lockedCode->getKey()],
                'created_at' => now(),
            ]);
        }, attempts: 3);
    }

    public function requestMembership(User $user, string $plainCode): InstitutionJoinRequest
    {
        $normalized = $this->normalize($plainCode);
        if ($normalized === null || $user->isDisabled()) {
            throw new JoinCodeUnavailableException('The join code is unavailable.');
        }

        $request = DB::transaction(function () use ($user, $normalized): InstitutionJoinRequest {
            $reference = InstitutionJoinCode::query()
                ->select(['id', 'institution_id'])
                ->where('token_hash', $this->hash($normalized))
                ->first();
            if ($reference === null) {
                throw new JoinCodeUnavailableException('The join code is unavailable.');
            }

            $institution = Institution::query()->lockForUpdate()->find($reference->institution_id);
            $code = InstitutionJoinCode::query()->whereKey($reference->getKey())->lockForUpdate()->first();
            $lockedUser = User::query()->lockForUpdate()->find($user->getKey());
            if ($institution === null
                || $institution->status !== InstitutionStatus::Active
                || $code === null
                || ! $code->isRedeemable()
                || $lockedUser === null
                || $lockedUser->isDisabled()) {
                throw new JoinCodeUnavailableException('The join code is unavailable.');
            }

            if (InstitutionMembership::query()
                ->where('institution_id', $institution->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->where('status', InstitutionMembershipStatus::Active->value)
                ->exists()) {
                throw new JoinCodeUnavailableException('The join code is unavailable.');
            }

            $pending = InstitutionJoinRequest::query()
                ->where('institution_id', $institution->getKey())
                ->where('user_id', $lockedUser->getKey())
                ->where('status', InstitutionJoinRequestStatus::Pending->value)
                ->lockForUpdate()
                ->first();
            if ($pending !== null) {
                return $pending;
            }

            $request = InstitutionJoinRequest::query()->create([
                'institution_id' => $institution->getKey(),
                'user_id' => $lockedUser->getKey(),
                'join_code_id' => $code->getKey(),
                'status' => InstitutionJoinRequestStatus::Pending,
                'requested_at' => now(),
            ]);
            $code->forceFill(['use_count' => $code->use_count + 1])->save();
            IdentityAudit::query()->create([
                'target_user_id' => $lockedUser->getKey(),
                'institution_id' => $institution->getKey(),
                'event' => 'membership_request.created',
                'metadata' => [
                    'join_request_id' => $request->getKey(),
                    'join_code_id' => $code->getKey(),
                ],
                'created_at' => now(),
            ]);

            return $request;
        }, attempts: 3);

        return $request->loadMissing('institution');
    }

    public function decide(User $actor, InstitutionJoinRequest $request, bool $approve): InstitutionJoinRequest
    {
        return DB::transaction(function () use ($actor, $request, $approve): InstitutionJoinRequest {
            $reference = InstitutionJoinRequest::query()
                ->select(['id', 'institution_id'])
                ->find($request->getKey());
            if ($reference === null) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }

            $institution = Institution::query()->lockForUpdate()->find($reference->institution_id);
            $lockedActor = User::query()->lockForUpdate()->find($actor->getKey());
            $lockedRequest = InstitutionJoinRequest::query()
                ->whereKey($reference->getKey())
                ->where('institution_id', $reference->institution_id)
                ->lockForUpdate()
                ->first();
            if ($institution === null || $lockedActor === null || $lockedRequest === null) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            $this->access->authorizeLearnerManagement($lockedActor, $institution);
            if ($lockedRequest->status !== InstitutionJoinRequestStatus::Pending) {
                throw new RuntimeException('This membership request has already been decided.');
            }

            if ($approve) {
                $membership = InstitutionMembership::query()->firstOrNew([
                    'institution_id' => $institution->getKey(),
                    'user_id' => $lockedRequest->user_id,
                ]);
                if ($membership->exists && $membership->status !== InstitutionMembershipStatus::Active) {
                    throw new RuntimeException('A prior restricted membership requires a separate reviewed restoration.');
                }
                $hasDefault = InstitutionMembership::query()
                    ->where('user_id', $lockedRequest->user_id)
                    ->where('status', InstitutionMembershipStatus::Active->value)
                    ->where('is_default', true)
                    ->exists();
                $membership->fill([
                    'status' => InstitutionMembershipStatus::Active,
                    'is_default' => ! $hasDefault,
                    'revoked_at' => null,
                ]);
                if (! $membership->exists) {
                    $membership->fill([
                        'provenance' => 'classroom_join_code',
                        'joined_at' => now(),
                    ]);
                }
                $membership->save();

                InstitutionRoleAssignment::query()->firstOrCreate([
                    'institution_membership_id' => $membership->getKey(),
                    'role' => InstitutionRole::Learner->value,
                ], [
                    'assigned_by_user_id' => $lockedActor->getKey(),
                    'assigned_at' => now(),
                ])->forceFill(['revoked_at' => null])->save();
            }

            $lockedRequest->forceFill([
                'status' => $approve ? InstitutionJoinRequestStatus::Approved : InstitutionJoinRequestStatus::Rejected,
                'decided_by_user_id' => $lockedActor->getKey(),
                'decided_at' => now(),
            ])->save();
            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'target_user_id' => $lockedRequest->user_id,
                'institution_id' => $institution->getKey(),
                'event' => $approve ? 'membership_request.approved' : 'membership_request.rejected',
                'metadata' => ['join_request_id' => $lockedRequest->getKey()],
                'created_at' => now(),
            ]);

            return $lockedRequest->fresh();
        }, attempts: 3);
    }

    private function generateCode(): string
    {
        $code = '';
        $last = strlen(self::CODE_ALPHABET) - 1;
        for ($index = 0; $index < self::CODE_LENGTH; $index++) {
            $code .= self::CODE_ALPHABET[random_int(0, $last)];
        }

        return $code;
    }

    private function normalize(string $code): ?string
    {
        $normalized = strtoupper(preg_replace('/[\s-]+/', '', trim($code)) ?? '');

        return preg_match('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{16}$/', $normalized) === 1
            ? $normalized
            : null;
    }

    private function format(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    private function hash(string $code): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for join-code hashing.');
        }

        return hash_hmac('sha256', $code, $key);
    }
}
