<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivilegedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->requiresMfa() || $this->isSecurityRoute($request)) {
            return $next($request);
        }

        $verifiedAt = (int) $request->session()->get('auth.mfa_verified_at', 0);
        if (! $user->hasStrongMfa() || $verifiedAt <= 0) {
            return redirect()->route('security.index')->with(
                'warning',
                $user->hasStrongMfa()
                    ? __('Confirm a passkey or sign in again with TOTP before continuing.')
                    : __('Your role requires a passkey or confirmed authenticator-app code before privileged work is available.'),
            );
        }

        return $next($request);
    }

    private function isSecurityRoute(Request $request): bool
    {
        return $request->routeIs([
            'security.*', 'mfa.*', 'passkey.*', 'logout',
            'verification.*', 'policies.*', 'locale.switch',
        ]);
    }
}
