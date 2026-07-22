<?php

namespace App\Services;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Enums\LegacyInstitutionState;
use App\Enums\UserRole;
use App\Exceptions\InvitationUnavailableException;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\IdentityAudit;
use App\Models\Institution;
use App\Models\InstitutionInvitation;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class InstitutionInvitationService
{
    public function __construct(
        private readonly InstitutionAccessService $access,
        private readonly CourseAccessService $courses,
        private readonly CourseEnrollmentService $enrollments,
    ) {}

    /** @return array{invitation: InstitutionInvitation, token: string} */
    public function issue(
        User $actor,
        Institution $institution,
        string $targetEmail,
        ?CourseOffering $offering = null,
    ): array {
        $email = User::canonicalEmail($targetEmail);
        $ttlHours = max(1, min(168, (int) config('identity.invitation.ttl_hours', 72)));
        $token = $this->newToken();

        $invitation = DB::transaction(function () use ($actor, $institution, $email, $ttlHours, $token, $offering): InstitutionInvitation {
            $lockedInstitution = Institution::query()->lockForUpdate()->find($institution->getKey());
            if ($lockedInstitution === null || $lockedInstitution->status !== InstitutionStatus::Active) {
                throw new RuntimeException('The selected institution is not active.');
            }
            $lockedOffering = $offering === null
                ? null
                : CourseOffering::query()->lockForUpdate()->find($offering->getKey());
            $lockedActor = $this->authorizeInvitationActor($actor, $lockedInstitution, $lockedOffering);

            $emailHash = $this->emailHash($email);
            $superseded = InstitutionInvitation::query()
                ->where('institution_id', $lockedInstitution->getKey())
                ->where('course_offering_id', $lockedOffering?->getKey())
                ->where('target_email_hash', $emailHash)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();
            foreach ($superseded as $priorInvitation) {
                $priorInvitation->forceFill([
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $lockedActor->getKey(),
                ])->save();
                IdentityAudit::query()->create([
                    'actor_user_id' => $lockedActor->getKey(),
                    'institution_id' => $lockedInstitution->getKey(),
                    'invitation_id' => $priorInvitation->getKey(),
                    'event' => 'invitation.superseded',
                    'created_at' => now(),
                ]);
            }

            $invitation = InstitutionInvitation::query()->create([
                'institution_id' => $lockedInstitution->getKey(),
                'course_offering_id' => $lockedOffering?->getKey(),
                'issued_by_user_id' => $lockedActor->getKey(),
                'target_email_ciphertext' => $email,
                'target_email_hash' => $emailHash,
                'token_hash' => $this->tokenHash($token),
                'expires_at' => now()->addHours($ttlHours),
                'use_limit' => 1,
                'use_count' => 0,
            ]);

            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'institution_id' => $lockedInstitution->getKey(),
                'invitation_id' => $invitation->getKey(),
                'event' => 'invitation.issued',
                'metadata' => [
                    'expires_at' => $invitation->expires_at->toAtomString(),
                    'course_offering_id' => $lockedOffering?->getKey(),
                ],
                'created_at' => now(),
            ]);

            return $invitation;
        }, 3);

        return ['invitation' => $invitation, 'token' => $token];
    }

    public function findRedeemable(string $token): ?InstitutionInvitation
    {
        if (! $this->hasValidTokenShape($token)) {
            return null;
        }

        $invitation = InstitutionInvitation::query()
            ->with(['institution', 'offering'])
            ->where('token_hash', $this->tokenHash($token))
            ->first();

        $offering = $invitation?->course_offering_id === null
            ? null
            : CourseOffering::query()->find($invitation->course_offering_id);

        return $invitation !== null
            && $this->targetEmail($invitation) !== null
            && $invitation->institution->status === InstitutionStatus::Active
            && ($invitation->course_offering_id === null || $offering?->acceptsEnrollments() === true)
            && $invitation->isRedeemable()
                ? $invitation
                : null;
    }

    /** @return array{user: User, institution: Institution, membership: InstitutionMembership, offering: CourseOffering|null, enrollment: CourseEnrollment|null, created: bool, sessions_revoked: int} */
    public function redeem(string $token, ?User $authenticatedUser, ?array $newUser): array
    {
        if (! $this->hasValidTokenShape($token)) {
            throw new InvitationUnavailableException;
        }

        return DB::transaction(function () use ($token, $authenticatedUser, $newUser): array {
            $tokenHash = $this->tokenHash($token);
            $invitationReference = InstitutionInvitation::query()
                ->select(['id', 'institution_id', 'course_offering_id'])
                ->where('token_hash', $tokenHash)
                ->first();
            if ($invitationReference === null) {
                throw new InvitationUnavailableException;
            }

            // Lock the institution before its invitation everywhere. This keeps
            // issue, revoke, and redeem on one deterministic lock order.
            $institution = Institution::query()->lockForUpdate()->find($invitationReference->institution_id);
            if ($institution === null || $institution->status !== InstitutionStatus::Active) {
                throw new InvitationUnavailableException;
            }
            $invitation = InstitutionInvitation::query()
                ->whereKey($invitationReference->getKey())
                ->where('token_hash', $tokenHash)
                ->where('institution_id', $institution->getKey())
                ->lockForUpdate()
                ->first();
            if ($invitation === null || ! $invitation->isRedeemable()) {
                throw new InvitationUnavailableException;
            }
            $offering = $invitationReference->course_offering_id === null
                ? null
                : CourseOffering::query()->lockForUpdate()->find($invitationReference->course_offering_id);
            if ($invitationReference->course_offering_id !== null
                && ($offering === null
                    || $offering->institution_id !== $institution->getKey()
                    || ! $offering->acceptsEnrollments())) {
                throw new InvitationUnavailableException;
            }

            $targetEmail = $this->targetEmail($invitation);
            if ($targetEmail === null) {
                throw new InvitationUnavailableException;
            }
            $created = false;

            if ($authenticatedUser !== null) {
                $user = User::query()
                    ->whereKey($authenticatedUser->getKey())
                    ->lockForUpdate()
                    ->first();
                if ($user === null
                    || $user->isDisabled()
                    || ! hash_equals($invitation->target_email_hash, $this->emailHash($user->email))) {
                    throw new InvitationUnavailableException;
                }
            } else {
                if (User::query()->where('email', $targetEmail)->exists() || $newUser === null) {
                    throw new InvitationUnavailableException;
                }

                try {
                    $user = User::query()->create([
                        'name' => $newUser['name'],
                        'instansi' => $institution->name_id,
                        'email' => $targetEmail,
                        'password' => $newUser['password'],
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Another institution invitation may win the canonical-email
                    // insert race. Keep the public contract bounded and roll this
                    // token/membership transaction back rather than leaking SQL.
                    throw new InvitationUnavailableException;
                }
                $user->forceFill([
                    'role' => UserRole::Learner,
                    'legacy_institution_state' => LegacyInstitutionState::Mapped,
                    'email_verified_at' => now(),
                ])->save();
                $created = true;
            }

            $membership = InstitutionMembership::query()->firstOrNew([
                'user_id' => $user->getKey(),
                'institution_id' => $institution->getKey(),
            ]);
            $hasActiveMembership = $membership->exists
                && $membership->status === InstitutionMembershipStatus::Active;

            if (! $hasActiveMembership) {
                $hasAnyActiveMembership = InstitutionMembership::query()
                    ->where('user_id', $user->getKey())
                    ->where('status', InstitutionMembershipStatus::Active->value)
                    ->exists();

                $membership->fill([
                    'status' => InstitutionMembershipStatus::Active,
                    'is_default' => ! $hasAnyActiveMembership,
                    'revoked_at' => null,
                ]);
                if (! $membership->exists) {
                    $membership->fill([
                        'provenance' => 'individual_invitation',
                        'joined_at' => now(),
                    ]);
                }
                $membership->save();

            }

            // A class invitation is also an explicit learner-role grant. This
            // must run for an existing active staff membership as well as for a
            // newly-created membership because one person may hold both roles.
            InstitutionRoleAssignment::query()->firstOrCreate([
                'institution_membership_id' => $membership->getKey(),
                'role' => InstitutionRole::Learner->value,
            ], [
                'assigned_by_user_id' => $invitation->issued_by_user_id,
                'assigned_at' => now(),
            ])->forceFill(['revoked_at' => null])->save();

            $enrollment = null;
            if ($offering !== null) {
                $issuer = User::query()->find($invitation->issued_by_user_id);
                $enrollment = $this->enrollments->enrollFromApprovedConnection(
                    $offering,
                    $membership,
                    $issuer,
                    __('classes.events.invitation_enrollment'),
                );
            }

            $invitation->forceFill([
                'use_count' => $invitation->use_count + 1,
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->getKey(),
            ])->save();

            // Revoke every prior login inside the same transaction as the
            // enrollment change. The controller regenerates and persists the
            // accepting request's replacement session only after commit.
            $sessionsRevoked = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();

            IdentityAudit::query()->create([
                'target_user_id' => $user->getKey(),
                'institution_id' => $institution->getKey(),
                'invitation_id' => $invitation->getKey(),
                'event' => 'invitation.redeemed',
                'metadata' => [
                    'created_account' => $created,
                    'course_offering_id' => $offering?->getKey(),
                ],
                'created_at' => now(),
            ]);
            IdentityAudit::query()->create([
                'target_user_id' => $user->getKey(),
                'institution_id' => $institution->getKey(),
                'event' => 'membership.sessions_revoked',
                'metadata' => ['sessions_revoked' => $sessionsRevoked],
                'created_at' => now(),
            ]);

            return [
                'user' => $user->fresh(),
                'institution' => $institution,
                'membership' => $membership->fresh(),
                'offering' => $offering,
                'enrollment' => $enrollment,
                'created' => $created,
                'sessions_revoked' => $sessionsRevoked,
            ];
        }, 3);
    }

    public function revoke(User $actor, InstitutionInvitation $invitation): void
    {
        DB::transaction(function () use ($actor, $invitation): void {
            $institution = Institution::query()->lockForUpdate()->find($invitation->institution_id);
            if ($institution === null || $institution->status !== InstitutionStatus::Active) {
                throw new InvitationUnavailableException;
            }
            $offering = $invitation->course_offering_id === null
                ? null
                : CourseOffering::query()->lockForUpdate()->find($invitation->course_offering_id);
            if ($invitation->course_offering_id !== null && $offering === null) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            $lockedActor = $this->authorizeInvitationActor($actor, $institution, $offering);
            $locked = InstitutionInvitation::query()
                ->whereKey($invitation->getKey())
                ->where('institution_id', $institution->getKey())
                ->lockForUpdate()
                ->first();
            if ($locked === null || $locked->revoked_at !== null || $locked->accepted_at !== null) {
                throw new InvitationUnavailableException;
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $lockedActor->getKey(),
            ])->save();

            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'institution_id' => $locked->institution_id,
                'invitation_id' => $locked->getKey(),
                'event' => 'invitation.revoked',
                'created_at' => now(),
            ]);
        }, 3);
    }

    /**
     * Compensate for a synchronous mail failure without retaining the raw token
     * in a queue or log. A token that was accepted during an ambiguous transport
     * outcome remains accepted; otherwise it is revoked and audited.
     */
    public function revokeAfterDeliveryFailure(InstitutionInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $institution = Institution::query()->lockForUpdate()->find($invitation->institution_id);
            if ($institution === null) {
                throw new InvitationUnavailableException;
            }
            $locked = InstitutionInvitation::query()
                ->whereKey($invitation->getKey())
                ->where('institution_id', $institution->getKey())
                ->lockForUpdate()
                ->first();
            if ($locked === null || $locked->accepted_at !== null || $locked->revoked_at !== null) {
                return;
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $locked->issued_by_user_id,
            ])->save();
            IdentityAudit::query()->create([
                'actor_user_id' => $locked->issued_by_user_id,
                'institution_id' => $locked->institution_id,
                'invitation_id' => $locked->getKey(),
                'event' => 'invitation.delivery_failed',
                'created_at' => now(),
            ]);
        }, 3);
    }

    public function maskedTarget(InstitutionInvitation $invitation): string
    {
        $email = $this->targetEmail($invitation);
        if ($email === null) {
            return str_repeat('•', 8);
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('•', max(3, min(8, mb_strlen($local) - 1))).'@'.$domain;
    }

    private function emailHash(string $email): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for invitation email hashing.');
        }

        return hash_hmac('sha256', $email, $key);
    }

    /**
     * Revalidate the actor from locked database state inside the write
     * transaction. Controller policy checks remain useful for early rejection,
     * but cannot be the final authority if role or membership changes while the
     * request is in flight.
     */
    private function authorizeInvitationActor(
        User $actor,
        Institution $institution,
        ?CourseOffering $offering = null,
    ): User {
        $lockedActor = User::query()->lockForUpdate()->find($actor->getKey());
        if ($lockedActor === null || $lockedActor->isDisabled()) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        if ($offering === null) {
            $this->access->authorizeLearnerManagement($lockedActor, $institution);
        } elseif ($offering->institution_id !== $institution->getKey()
            || ! $offering->acceptsEnrollments()
            || ! $this->courses->canManageOffering($lockedActor, $offering)) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }

        return $lockedActor;
    }

    private function targetEmail(InstitutionInvitation $invitation): ?string
    {
        try {
            $email = User::canonicalEmail($invitation->target_email_ciphertext);

            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
                && hash_equals($invitation->target_email_hash, $this->emailHash($email))
                    ? $email
                    : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hasValidTokenShape(string $token): bool
    {
        return preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) === 1;
    }
}
