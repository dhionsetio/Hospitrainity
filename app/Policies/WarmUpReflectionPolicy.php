<?php

namespace App\Policies;

use App\Models\CourseOffering;
use App\Models\User;
use App\Models\WarmUpReflection;

class WarmUpReflectionPolicy
{
    public function view(User $user, WarmUpReflection $reflection): bool
    {
        if ((int) $reflection->user_id === (int) $user->getKey()) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (($user->isSupervisor() || $user->isAdmin()) && is_string($reflection->course_offering_id)) {
            return CourseOffering::query()
                ->whereKey($reflection->course_offering_id)
                ->whereHas('institution', function ($query) use ($user): void {
                    $query->whereHas('memberships', function ($mQuery) use ($user): void {
                        $mQuery->where('user_id', $user->getKey());
                    });
                })
                ->exists();
        }

        return false;
    }

    public function update(User $user, WarmUpReflection $reflection): bool
    {
        return (int) $reflection->user_id === (int) $user->getKey() && $reflection->state === 'draft';
    }
}
