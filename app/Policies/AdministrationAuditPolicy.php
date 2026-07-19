<?php

namespace App\Policies;

use App\Models\User;

class AdministrationAuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
