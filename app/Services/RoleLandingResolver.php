<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

final class RoleLandingResolver
{
    public function routeName(User $user): string
    {
        $role = $user->role;

        if ($role === null) {
            throw new AuthorizationException(__('This account does not have a supported role.'));
        }

        return $role->landingRoute();
    }

    public function url(User $user): string
    {
        return route($this->routeName($user));
    }

    public function redirect(User $user): RedirectResponse
    {
        return redirect()->route($this->routeName($user));
    }
}
