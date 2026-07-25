<?php

namespace App\Services;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class CourseAccessService
{
    public function __construct(private readonly InstitutionAccessService $institutions) {}

    public function canCreateCourse(User $actor, Institution $institution): bool
    {
        return $this->activeTenant($actor, $institution)
            && ($this->institutions->isSystemAdmin($actor)
                || $this->hasInstitutionRole($actor, $institution, InstitutionRole::InstitutionAdmin)
                || $this->hasInstitutionRole($actor, $institution, InstitutionRole::Instructor));
    }

    public function canManageCourse(User $actor, Course $course): bool
    {
        $institution = Institution::query()->find($course->institution_id);

        return $institution !== null
            && $this->activeTenant($actor, $institution)
            && ($this->institutions->isSystemAdmin($actor)
                || $this->hasInstitutionRole($actor, $institution, InstitutionRole::InstitutionAdmin));
    }

    public function canCreateRevision(User $actor, Course $course): bool
    {
        return $course->archived_at === null && $this->canManageCourse($actor, $course);
    }

    public function canCreateOffering(User $actor, Course $course): bool
    {
        return $this->canCreateRevision($actor, $course);
    }

    public function canManageOffering(User $actor, CourseOffering $offering): bool
    {
        return $offering->status !== CourseOfferingStatus::Archived
            && $this->hasStaffAccessToOffering($actor, $offering);
    }

    private function hasStaffAccessToOffering(User $actor, CourseOffering $offering): bool
    {
        $institution = Institution::query()->find($offering->institution_id);
        if ($institution === null || ! $this->activeTenant($actor, $institution)) {
            return false;
        }

        if ($this->institutions->isSystemAdmin($actor)
            || $this->hasInstitutionRole($actor, $institution, InstitutionRole::InstitutionAdmin)) {
            return true;
        }

        if (! $this->hasInstitutionRole($actor, $institution, InstitutionRole::Instructor)) {
            return false;
        }

        return $offering->teachingAssignments()
            ->whereNull('revoked_at')
            ->whereHas('membership', fn ($query) => $query
                ->where('user_id', $actor->getKey())
                ->where('institution_id', $institution->getKey())
                ->where('status', InstitutionMembershipStatus::Active->value))
            ->exists();
    }

    public function canViewOffering(User $actor, CourseOffering $offering): bool
    {
        if ($this->hasStaffAccessToOffering($actor, $offering)) {
            return true;
        }

        $institution = Institution::query()->find($offering->institution_id);
        if ($institution === null
            || ! $this->activeTenant($actor, $institution)
            || ! $this->hasInstitutionRole($actor, $institution, InstitutionRole::Learner)) {
            return false;
        }

        return $offering->enrollments()
            ->where('status', CourseEnrollmentStatus::Active->value)
            ->whereHas('membership', fn ($query) => $query
                ->where('user_id', $actor->getKey())
                ->where('institution_id', $institution->getKey())
                ->where('status', InstitutionMembershipStatus::Active->value))
            ->exists();
    }

    public function canViewCourse(User $actor, Course $course): bool
    {
        if ($this->canManageCourse($actor, $course)) {
            return true;
        }

        foreach ($course->offerings()->get() as $offering) {
            if ($offering instanceof CourseOffering && $this->canViewOffering($actor, $offering)) {
                return true;
            }
        }

        return false;
    }

    public function authorizeCourseManagement(User $actor, Course $course): void
    {
        if (! $this->canCreateRevision($actor, $course)) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }
    }

    public function authorizeOfferingManagement(User $actor, CourseOffering $offering): void
    {
        if (! $this->canManageOffering($actor, $offering)) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }
    }

    private function activeTenant(User $actor, Institution $institution): bool
    {
        $status = $institution->getAttribute('status');
        $active = $status instanceof InstitutionStatus
            ? $status === InstitutionStatus::Active
            : $status === InstitutionStatus::Active->value;

        return ! $actor->isDisabled() && $active;
    }

    private function hasInstitutionRole(User $actor, Institution $institution, InstitutionRole $role): bool
    {
        return InstitutionRoleAssignment::query()
            ->where('role', $role->value)
            ->whereNull('revoked_at')
            ->whereHas('membership', fn ($query) => $query
                ->where('user_id', $actor->getKey())
                ->where('institution_id', $institution->getKey())
                ->where('status', InstitutionMembershipStatus::Active->value))
            ->exists();
    }
}
