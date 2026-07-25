<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class RoleLandingResolver
{
    public function routeName(User $user): string
    {
        return app(WorkContext::class)->current(request(), $user)->landingRoute();
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
