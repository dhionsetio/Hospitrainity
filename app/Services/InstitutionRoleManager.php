<?php

namespace App\Services;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\IdentityAudit;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InstitutionRoleManager
{
    public function __construct(private readonly InstitutionAccessService $access) {}

    /** @return array{assignment: InstitutionRoleAssignment, sessions_revoked: int} */
    public function set(
        User $actor,
        InstitutionMembership $membership,
        InstitutionRole $role,
        bool $active,
    ): array {
        return DB::transaction(function () use ($actor, $membership, $role, $active): array {
            $reference = InstitutionMembership::query()
                ->select(['id', 'institution_id', 'user_id'])
                ->find($membership->getKey());
            if ($reference === null) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            $institution = Institution::query()->lockForUpdate()->find($reference->institution_id);
            $users = User::query()
                ->whereKey([$actor->getKey(), $reference->user_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()->keyBy('id');
            $lockedActor = $users->get($actor->getKey());
            $target = $users->get($reference->user_id);
            $lockedMembership = InstitutionMembership::query()
                ->whereKey($reference->getKey())
                ->where('institution_id', $reference->institution_id)
                ->lockForUpdate()->first();
            if ($institution === null || $lockedActor === null || $target === null || $lockedMembership === null) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            if ($lockedActor->is($target)) {
                throw ValidationException::withMessages([
                    'role' => __('You cannot change your own institution role.'),
                ]);
            }
            if ($lockedMembership->status !== InstitutionMembershipStatus::Active) {
                throw ValidationException::withMessages([
                    'role' => __('Only active institution memberships can receive roles.'),
                ]);
            }
            if (! $this->access->canManageStaff($lockedActor, $institution)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            if ($role === InstitutionRole::Learner) {
                throw new AuthorizationException(__('Learner membership is managed through the membership lifecycle.'));
            }
            if ($role === InstitutionRole::InstitutionAdmin && ! $this->access->isSystemAdmin($lockedActor)) {
                throw new AuthorizationException(__('Only System Admin can grant or revoke Institution Admin.'));
            }

            $assignment = InstitutionRoleAssignment::query()->firstOrNew([
                'institution_membership_id' => $lockedMembership->getKey(),
                'role' => $role->value,
            ]);
            if (! $assignment->exists && ! $active) {
                throw ValidationException::withMessages(['role' => __('That role is not active.')]);
            }
            if (! $assignment->exists) {
                $assignment->forceFill([
                    'assigned_by_user_id' => $lockedActor->getKey(),
                    'assigned_at' => now(),
                ]);
            }
            if ($assignment->exists && ($assignment->revoked_at === null) === $active) {
                throw ValidationException::withMessages(['role' => __('That role already has the requested state.')]);
            }
            $assignment->forceFill(['revoked_at' => $active ? null : now()])->save();

            $target->forceFill(['remember_token' => Str::random(60)])->save();
            $sessionsRevoked = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $target->getKey())
                ->delete();
            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'target_user_id' => $target->getKey(),
                'institution_id' => $institution->getKey(),
                'event' => $active ? 'institution_role.granted' : 'institution_role.revoked',
                'metadata' => [
                    'role' => $role->value,
                    'sessions_revoked' => $sessionsRevoked,
                ],
                'created_at' => now(),
            ]);

            return ['assignment' => $assignment->fresh(), 'sessions_revoked' => $sessionsRevoked];
        }, attempts: 3);
    }
}
