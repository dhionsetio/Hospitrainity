<?php

namespace App\Services;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Enums\PlatformRole;
use App\Models\Institution;
use App\Models\InstitutionRoleAssignment;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class InstitutionAccessService
{
    public function canManageLearners(User $actor, Institution $institution): bool
    {
        if ($actor->isDisabled() || $institution->status !== InstitutionStatus::Active) {
            return false;
        }

        if ($this->isSystemAdmin($actor)) {
            return true;
        }

        return $this->hasInstitutionRole(
            $actor,
            $institution,
            InstitutionRole::Instructor,
            InstitutionRole::InstitutionAdmin,
        );
    }

    public function canManageStaff(User $actor, Institution $institution): bool
    {
        return ! $actor->isDisabled()
            && ($this->isSystemAdmin($actor)
                || $this->hasInstitutionRole($actor, $institution, InstitutionRole::InstitutionAdmin));
    }

    public function authorizeLearnerManagement(User $actor, Institution $institution): void
    {
        if (! $this->canManageLearners($actor, $institution)) {
            throw new AuthorizationException(__('This action is not authorized.'));
        }
    }

    public function isSystemAdmin(User $actor): bool
    {
        return $actor->role?->value === 'superadmin'
            || PlatformRoleAssignment::query()
                ->where('user_id', $actor->getKey())
                ->where('role', PlatformRole::SystemAdmin->value)
                ->whereNull('revoked_at')
                ->exists();
    }

    private function hasInstitutionRole(User $actor, Institution $institution, InstitutionRole ...$roles): bool
    {
        return InstitutionRoleAssignment::query()
            ->whereIn('role', array_map(static fn (InstitutionRole $role): string => $role->value, $roles))
            ->whereNull('revoked_at')
            ->whereHas('membership', function ($query) use ($actor, $institution): void {
                $query->where('user_id', $actor->getKey())
                    ->where('institution_id', $institution->getKey())
                    ->where('status', InstitutionMembershipStatus::Active->value);
            })
            ->exists();
    }
}
