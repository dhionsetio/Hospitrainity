<?php

namespace App\Models;

use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkey as BasePasskey;

class Passkey extends BasePasskey
{
    protected static function booted(): void
    {
        static::deleting(function (self $passkey): void {
            $user = $passkey->user;
            if ($user instanceof User
                && $user->requiresMfa()
                && ! $user->hasConfirmedTotp()
                && $user->passkeys()->count() <= 1) {
                throw ValidationException::withMessages([
                    'passkey' => __('Add another passkey or confirm TOTP before removing the last required authenticator.'),
                ]);
            }
        });
    }
}
