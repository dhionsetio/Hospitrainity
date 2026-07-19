<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Auth\Access\Response;

class VocabularyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isContentAdministrator();
    }

    public function view(User $user, Vocabulary $vocabulary): Response
    {
        $published = $user->isLearner()
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
