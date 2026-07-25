<?php

namespace App\Policies;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Models\User;
use App\Services\InstitutionAccessService;
use App\Services\InstitutionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function __construct(private readonly InstitutionAccessService $access) {}

    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $actor->is($target);
    }

    public function promoteToSuperadmin(User $actor, User $target): bool
    {
        return $this->changeRole($actor, $target);
    }

    public function viewGlobalLearnerProgress(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function viewAggregateProgress(User $actor): bool
    {
        return $actor->isContentAdministrator();
    }

    public function viewLearnerProgress(User $actor, User $learner): Response
    {
        if (! $learner->isLearner()
            && ! $learner->institutionMemberships()
                ->where('status', InstitutionMembershipStatus::Active->value)
                ->whereHas('roleAssignments', fn ($query) => $query
                    ->where('role', InstitutionRole::Learner->value)
                    ->whereNull('revoked_at'))
                ->exists()) {
            return Response::denyAsNotFound();
        }

        if ($actor->isSuperAdmin()) {
            return Response::allow();
        }

        try {
            $institution = app(InstitutionContext::class)->current(request(), $actor);
        } catch (AuthorizationException) {
            return Response::denyAsNotFound();
        }

        if ($this->access->canManageLearners($actor, $institution)) {
            if ($learner->institutionMemberships()
                ->where('institution_id', $institution->getKey())
                ->where('status', InstitutionMembershipStatus::Active->value)
                ->whereHas('roleAssignments', fn ($query) => $query
                    ->where('role', InstitutionRole::Learner->value)
                    ->whereNull('revoked_at'))
                ->exists()) {
                return Response::allow();
            }
        }

        return Response::denyAsNotFound();
    }
}
