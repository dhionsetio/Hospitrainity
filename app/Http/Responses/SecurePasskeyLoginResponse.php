<?php

namespace App\Http\Responses;

use App\Services\RoleLandingResolver;
use App\Services\SecurityEventRecorder;
use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;

class SecurePasskeyLoginResponse implements PasskeyLoginResponse
{
    public function toResponse($request)
    {
        $request->session()->put(['auth.mfa_verified_at' => time(), 'auth.mfa_method' => 'passkey']);
        app(SecurityEventRecorder::class)->record(
            'authentication.passkey_succeeded',
            'allowed',
            $request->user(),
            $request->user()?->email,
            $request,
        );
        $target = app(RoleLandingResolver::class)->url($request->user());

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $target])
            : redirect()->to($target);
    }
}
