<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\WorkContextRole;
use App\Services\WorkContext;
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
            || ! collect($allowedRoles)->contains(fn (UserRole $role): bool => $this->matches($request, $role))
        ) {
            abort(403, __('This action is not authorized.'));
        }

        return $next($request);
    }

    private function matches(Request $request, UserRole $role): bool
    {
        $workRole = app(WorkContext::class)->current($request, $request->user());

        return match ($role) {
            UserRole::Learner => $workRole === WorkContextRole::Learner,
            UserRole::Admin => $workRole === WorkContextRole::ContentAuthor,
            UserRole::Superadmin => $workRole === WorkContextRole::SystemAdmin,
            UserRole::Supervisor => in_array($workRole, [WorkContextRole::Instructor, WorkContextRole::InstitutionAdmin], true),
        };
    }
}
