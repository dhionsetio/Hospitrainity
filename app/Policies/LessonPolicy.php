<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LessonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isContentAdministrator();
    }

    public function view(User $user, Lesson $lesson): Response
    {
        $published = $user->isLearner()
            && $lesson->module()->where('is_published', true)->exists();

        return $published ? Response::allow() : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->isSuperAdmin();
    }
}
