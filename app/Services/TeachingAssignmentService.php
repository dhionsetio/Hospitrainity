<?php

namespace App\Services;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\TeachingAssignmentRole;
use App\Models\CourseOffering;
use App\Models\IdentityAudit;
use App\Models\InstitutionMembership;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TeachingAssignmentService
{
    public function __construct(private readonly CourseAccessService $access) {}

    public function assign(
        User $actor,
        CourseOffering $offering,
        InstitutionMembership $membership,
        TeachingAssignmentRole $role,
    ): TeachingAssignment {
        return DB::transaction(function () use ($actor, $offering, $membership, $role): TeachingAssignment {
            $lockedOffering = CourseOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $lockedMembership = InstitutionMembership::query()->lockForUpdate()->findOrFail($membership->getKey());
            if (! $this->access->canManageOffering($lockedActor, $lockedOffering)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            if ($lockedMembership->institution_id !== $lockedOffering->institution_id
                || $lockedMembership->status !== InstitutionMembershipStatus::Active
                || ! $lockedMembership->roleAssignments()
                    ->where('role', InstitutionRole::Instructor->value)
                    ->whereNull('revoked_at')
                    ->exists()) {
                throw ValidationException::withMessages(['membership' => __('classes.errors.instructor_unavailable')]);
            }

            $activeForMember = TeachingAssignment::query()
                ->where('course_offering_id', $lockedOffering->getKey())
                ->where('institution_membership_id', $lockedMembership->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();
            if ($activeForMember?->role === $role) {
                return $activeForMember;
            }

            if ($role === TeachingAssignmentRole::Primary) {
                TeachingAssignment::query()
                    ->where('course_offering_id', $lockedOffering->getKey())
                    ->where('role', TeachingAssignmentRole::Primary->value)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->get()
                    ->each(fn (TeachingAssignment $assignment) => $assignment->forceFill(['revoked_at' => now()])->save());
            }
            if ($activeForMember !== null) {
                $activeForMember->forceFill(['revoked_at' => now()])->save();
            }

            $assignment = TeachingAssignment::query()->create([
                'course_offering_id' => $lockedOffering->getKey(),
                'institution_membership_id' => $lockedMembership->getKey(),
                'role' => $role,
                'assigned_by_user_id' => $lockedActor->getKey(),
                'assigned_at' => now(),
            ]);
            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'target_user_id' => $lockedMembership->user_id,
                'institution_id' => $lockedOffering->institution_id,
                'event' => 'class.instructor_assigned',
                'metadata' => [
                    'course_offering_id' => $lockedOffering->getKey(),
                    'teaching_assignment_id' => $assignment->getKey(),
                    'role' => $role->value,
                ],
                'created_at' => now(),
            ]);

            return $assignment;
        }, attempts: 3);
    }

    public function revoke(User $actor, TeachingAssignment $assignment): void
    {
        DB::transaction(function () use ($actor, $assignment): void {
            $locked = TeachingAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $offering = CourseOffering::query()->lockForUpdate()->findOrFail($locked->course_offering_id);
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            if (! $this->access->canManageOffering($lockedActor, $offering)) {
                throw new AuthorizationException(__('This action is not authorized.'));
            }
            if ($locked->revoked_at !== null) {
                return;
            }
            if ($locked->role === TeachingAssignmentRole::Primary) {
                throw ValidationException::withMessages([
                    'assignment' => __('classes.errors.primary_requires_replacement'),
                ]);
            }

            $locked->forceFill(['revoked_at' => now()])->save();
            IdentityAudit::query()->create([
                'actor_user_id' => $lockedActor->getKey(),
                'target_user_id' => $locked->membership()->value('user_id'),
                'institution_id' => $offering->institution_id,
                'event' => 'class.instructor_revoked',
                'metadata' => [
                    'course_offering_id' => $offering->getKey(),
                    'teaching_assignment_id' => $locked->getKey(),
                ],
                'created_at' => now(),
            ]);
        }, attempts: 3);
    }
}
