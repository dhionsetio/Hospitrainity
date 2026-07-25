<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class LocalTesterMfaBypass
{
    public function allows(?User $user, Request $request): bool
    {
        if ($user === null
            || config('authentication.mfa.local_tester_bypass') !== true
            || ! app()->environment('local')) {
            return false;
        }

        $remoteAddress = (string) $request->server('REMOTE_ADDR', '');
        $host = strtolower($request->getHost());
        if (! in_array($remoteAddress, ['127.0.0.1', '::1'], true)
            || ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        $configuredDemoEmails = array_map(
            static fn (array $account): mixed => $account['email'] ?? null,
            config('identity.demo_seed.accounts', []),
        );
        $knownEmails = array_map(
            static fn (mixed $email): string => User::canonicalEmail((string) $email),
            array_filter([
                ...config('identity.known_demo_emails', []),
                ...$configuredDemoEmails,
            ], static fn (mixed $email): bool => is_string($email) && trim($email) !== ''),
        );

        return in_array(User::canonicalEmail($user->email), $knownEmails, true);
    }
}
