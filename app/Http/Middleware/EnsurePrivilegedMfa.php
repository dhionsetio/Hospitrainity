<?php

namespace App\Http\Middleware;

use App\Services\LocalTesterMfaBypass;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivilegedMfa
{
    public function __construct(private readonly LocalTesterMfaBypass $testerMfaBypass) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->requiresMfa()) {
            return $next($request);
        }

        if ($this->testerMfaBypass->allows($user, $request)) {
            return $next($request);
        }

        $verifiedAt = (int) $request->session()->get('auth.mfa_verified_at', 0);
        $stepUpSeconds = (int) config('authentication.mfa.step_up_seconds', 900);
        $assuranceAge = time() - $verifiedAt;
        $assuranceIsFresh = $verifiedAt > 0
            && $stepUpSeconds > 0
            && $assuranceAge >= 0
            && $assuranceAge <= $stepUpSeconds;

        if (! $user->hasStrongMfa() || ! $assuranceIsFresh) {
            return redirect()->route('security.index')->with(
                'warning',
                ! $user->hasStrongMfa()
                    ? __('Your role requires a passkey or confirmed authenticator-app code before privileged work is available.')
                    : ($verifiedAt > 0
                        ? __('Your MFA confirmation expired. Sign in again or confirm with a passkey before continuing.')
                        : __('Confirm a passkey or sign in again with TOTP before continuing.')),
            );
        }

        return $next($request);
    }
}
