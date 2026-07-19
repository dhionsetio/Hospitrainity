<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\User;

class ExercisePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isContentAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $user->isSuperAdmin();
    }
}
