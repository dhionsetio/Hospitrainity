<?php

namespace App\Services;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\PlatformRole;
use App\Enums\UserCapability;
use App\Enums\UserRole;
use App\Models\AdministrationAudit;
use App\Models\InstitutionRoleAssignment;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Models\UserCapabilityAssignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UserRoleManager
{
    /**
     * @return array{target: User, old_role: UserRole, new_role: UserRole, audit: AdministrationAudit, sessions_revoked: int}
     */
    public function change(
        User $actor,
        User $target,
        UserRole $newRole,
        UserRole $expectedRole,
        string $reason,
        ?string $ipAddress,
        ?string $userAgent,
        bool $superadminPromotion = false,
    ): array {
        return DB::transaction(function () use (
            $actor,
            $target,
            $newRole,
            $expectedRole,
            $reason,
            $ipAddress,
            $userAgent,
            $superadminPromotion,
        ): array {
            $lockedUsers = User::query()
                ->whereKey([$actor->getKey(), $target->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var User|null $lockedActor */
            $lockedActor = $lockedUsers->get($actor->getKey());
            /** @var User|null $lockedTarget */
            $lockedTarget = $lockedUsers->get($target->getKey());

            if ($lockedActor === null || $lockedTarget === null || ! $lockedActor->isSuperAdmin()) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }

            if ($lockedActor->is($lockedTarget)) {
                throw ValidationException::withMessages([
                    'role' => __('admin.cannot_change_own_role'),
                ]);
            }

            if ($lockedTarget->role !== $expectedRole) {
                throw ValidationException::withMessages([
                    'role' => __('admin.role_changed_since_page_load'),
                ]);
            }

            if ($lockedTarget->role === $newRole) {
                throw ValidationException::withMessages([
                    'role' => __('admin.role_must_change'),
                ]);
            }

            if ($superadminPromotion !== ($newRole === UserRole::Superadmin)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }

            if ($newRole->isElevated() && ! $lockedTarget->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'role' => __('admin.elevated_role_requires_verified_email'),
                ]);
            }

            if ($lockedTarget->isSuperAdmin()) {
                $superadminIds = User::query()
                    ->where('role', UserRole::Superadmin->value)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id');

                if ($superadminIds->count() <= 1) {
                    throw ValidationException::withMessages([
                        'role' => __('admin.last_superadmin_cannot_be_demoted'),
                    ]);
                }
            }

            $oldRole = $lockedTarget->role;
            $lockedTarget->forceFill([
                'role' => $newRole,
                'remember_token' => Str::random(60),
            ])->save();
            $this->synchronizeLegacyCompatibilityAssignments($lockedActor, $lockedTarget, $newRole);

            $sessionsRevoked = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $lockedTarget->getKey())
                ->delete();

            $audit = AdministrationAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'target_user_id' => $lockedTarget->getKey(),
                'event' => $superadminPromotion ? 'user.promoted_superadmin' : 'user.role_changed',
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'reason' => trim($reason),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 255, ''),
                'metadata' => ['sessions_revoked' => $sessionsRevoked],
                'created_at' => now(),
            ]);

            return [
                'target' => $lockedTarget->fresh(),
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'audit' => $audit,
                'sessions_revoked' => $sessionsRevoked,
            ];
        }, 3);
    }

    private function synchronizeLegacyCompatibilityAssignments(User $actor, User $target, UserRole $newRole): void
    {
        $this->setPlatformRole($actor, $target, $newRole === UserRole::Superadmin);
        $this->setContentAuthor($actor, $target, $newRole === UserRole::Admin);

        $membershipIds = $target->institutionMemberships()
            ->where('status', InstitutionMembershipStatus::Active->value)
            ->lockForUpdate()
            ->pluck('id');
        foreach ($membershipIds as $membershipId) {
            foreach (InstitutionRole::cases() as $institutionRole) {
                $shouldBeActive = match ($newRole) {
                    UserRole::Learner => $institutionRole === InstitutionRole::Learner,
                    UserRole::Supervisor, UserRole::Admin => $institutionRole === InstitutionRole::Instructor,
                    UserRole::Superadmin => $institutionRole === InstitutionRole::InstitutionAdmin,
                };
                $assignment = InstitutionRoleAssignment::query()->firstOrNew([
                    'institution_membership_id' => $membershipId,
                    'role' => $institutionRole->value,
                ]);
                if (! $assignment->exists && ! $shouldBeActive) {
                    continue;
                }
                if (! $assignment->exists) {
                    $assignment->forceFill([
                        'assigned_by_user_id' => $actor->getKey(),
                        'assigned_at' => now(),
                    ]);
                }
                $assignment->forceFill(['revoked_at' => $shouldBeActive ? null : now()])->save();
            }
        }
    }

    private function setPlatformRole(User $actor, User $target, bool $active): void
    {
        $assignment = PlatformRoleAssignment::query()->firstOrNew([
            'user_id' => $target->getKey(),
            'role' => PlatformRole::SystemAdmin->value,
        ]);
        if (! $assignment->exists && ! $active) {
            return;
        }
        if (! $assignment->exists) {
            $assignment->forceFill(['assigned_by_user_id' => $actor->getKey(), 'assigned_at' => now()]);
        }
        $assignment->forceFill(['revoked_at' => $active ? null : now()])->save();
    }

    private function setContentAuthor(User $actor, User $target, bool $active): void
    {
        $assignment = UserCapabilityAssignment::query()->firstOrNew([
            'user_id' => $target->getKey(),
            'capability' => UserCapability::ContentAuthor->value,
        ]);
        if (! $assignment->exists && ! $active) {
            return;
        }
        if (! $assignment->exists) {
            $assignment->forceFill(['assigned_by_user_id' => $actor->getKey(), 'assigned_at' => now()]);
        }
        $assignment->forceFill(['revoked_at' => $active ? null : now()])->save();
    }
}
