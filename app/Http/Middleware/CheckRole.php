<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $allowedRoles = array_map(
            static fn (string $role): ?UserRole => UserRole::tryFrom($role),
            $roles,
        );

        if (
            $request->user() === null
            || in_array(null, $allowedRoles, true)
            || ! in_array($request->user()->role, $allowedRoles, true)
        ) {
            abort(403, __('This action is not authorized.'));
        }

        return $next($request);
    }
}
