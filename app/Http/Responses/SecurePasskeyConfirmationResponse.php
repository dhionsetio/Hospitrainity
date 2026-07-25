<?php

namespace App\Http\Responses;

use App\Services\SecurityEventRecorder;
use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyConfirmationResponse;

class SecurePasskeyConfirmationResponse implements PasskeyConfirmationResponse
{
    public function toResponse($request)
    {
        $request->session()->put(['auth.mfa_verified_at' => time(), 'auth.mfa_method' => 'passkey']);
        app(SecurityEventRecorder::class)->record(
            'authentication.passkey_confirmed',
            'allowed',
            $request->user(),
            $request->user()?->email,
            $request,
        );
        $target = redirect()->intended(route('security.index'))->getTargetUrl();

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $target])
            : redirect()->to($target);
    }
}
