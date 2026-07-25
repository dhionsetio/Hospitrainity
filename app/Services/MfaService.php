<?php

namespace App\Services;

use App\Models\MfaRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class MfaService
{
    public function __construct(private readonly TwoFactorAuthenticationProvider $totp) {}

    public function beginTotp(User $user): string
    {
        $secret = $this->totp->generateSecretKey();
        $user->forceFill(['two_factor_secret' => Crypt::encryptString($secret), 'two_factor_confirmed_at' => null])->save();

        return $secret;
    }

    /** @return list<string> */
    public function confirmTotp(User $user, string $code): array
    {
        if (! $this->verifyTotp($user, $code)) {
            return [];
        }

        return DB::transaction(function () use ($user): array {
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();

            return $this->regenerateRecoveryCodes($user);
        }, attempts: 3);
    }

    public function verifyTotp(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        return $this->totp->verify(Crypt::decryptString($user->two_factor_secret), $code);
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $digest = $this->digest($user, $code);

        return DB::transaction(function () use ($user, $digest): bool {
            $recovery = MfaRecoveryCode::query()
                ->where('user_id', $user->getKey())
                ->where('code_digest', $digest)
                ->whereNull('used_at')
                ->lockForUpdate()
                ->first();
            if ($recovery === null) {
                return false;
            }
            $recovery->forceFill(['used_at' => now()])->save();

            return true;
        }, attempts: 3);
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            MfaRecoveryCode::query()->where('user_id', $user->getKey())->delete();
            $codes = [];
            for ($index = 0; $index < (int) config('authentication.mfa.recovery_code_count', 10); $index++) {
                $code = implode('-', str_split(strtolower(bin2hex(random_bytes(8))), 4));
                $codes[] = $code;
                MfaRecoveryCode::query()->create([
                    'user_id' => $user->getKey(),
                    'code_digest' => $this->digest($user, $code),
                    'created_at' => now(),
                ]);
            }

            return $codes;
        }, attempts: 3);
    }

    public function disableTotp(User $user): void
    {
        DB::transaction(function () use ($user): void {
            MfaRecoveryCode::query()->where('user_id', $user->getKey())->delete();
            $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        }, attempts: 3);
    }

    private function digest(User $user, string $code): string
    {
        return hash_hmac('sha256', strtolower(trim($code)).'|'.$user->getKey(), (string) config('app.key'));
    }
}
