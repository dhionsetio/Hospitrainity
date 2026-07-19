<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
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
        if (! $learner->isLearner()) {
            return Response::denyAsNotFound();
        }

        if ($actor->isSuperAdmin()) {
            return Response::allow();
        }

        $institution = (string) $actor->instansi;
        if ($actor->isSupervisor()
            && trim($institution) !== ''
            && hash_equals($institution, (string) $learner->instansi)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }
}
