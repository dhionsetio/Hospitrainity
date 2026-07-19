<?php

namespace App\Policies;

use App\Models\Module;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isContentAdministrator();
    }

    public function view(User $user, Module $module): Response
    {
        return $user->isLearner() && $module->is_published
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Module $module): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Module $module): bool
    {
        return $user->isSuperAdmin();
    }
}
