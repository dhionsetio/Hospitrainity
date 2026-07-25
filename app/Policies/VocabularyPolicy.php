<?php

namespace App\Policies;

use App\Enums\WorkContextRole;
use App\Models\User;
use App\Models\Vocabulary;
use App\Services\WorkContext;
use Illuminate\Auth\Access\Response;

class VocabularyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isContentAdministrator();
    }

    public function view(User $user, Vocabulary $vocabulary): Response
    {
        $published = app(WorkContext::class)->current(request(), $user) === WorkContextRole::Learner
            && $vocabulary->lesson()
                ->whereHas('module', fn ($query) => $query->where('is_published', true))
                ->exists();

        return $published ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Vocabulary $vocabulary): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Vocabulary $vocabulary): bool
    {
        return $user->isSuperAdmin();
    }
}
