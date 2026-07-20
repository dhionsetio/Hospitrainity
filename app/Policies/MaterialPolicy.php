<?php

namespace App\Policies;

use App\Enums\WorkContextRole;
use App\Models\Material;
use App\Models\User;
use App\Services\WorkContext;
use Illuminate\Auth\Access\Response;

class MaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isContentAdministrator();
    }

    public function view(User $user, Material $material): Response
    {
        $published = app(WorkContext::class)->current(request(), $user) === WorkContextRole::Learner
            && $material->lesson()
                ->whereHas('module', fn ($query) => $query->where('is_published', true))
                ->exists();

        return $published ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Material $material): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Material $material): bool
    {
        return $user->isSuperAdmin();
    }
}
