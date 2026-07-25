<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use RuntimeException;

final class DemoSeedGuard
{
    /**
     * Fail before a seeder performs any database read or write.
     *
     * @return list<array{name: string, institution_key: string, legacy_institution: string, email: string, password: string, role: UserRole}>
     */
    public function authorizedAccounts(): array
    {
        $environment = app()->environment();
        $allowed = config('identity.demo_seed.allowed_environments', []);

        if (! is_array($allowed)
            || ! in_array($environment, $allowed, true)
            || config('identity.demo_seed.enabled') !== true) {
            throw new RuntimeException(
                'Database seeding is disabled. It requires an explicit demo-seed flag in a local or testing environment; production is never allowed.',
            );
        }

        $configured = config('identity.demo_seed.accounts');
        if (! is_array($configured) || $configured === []) {
            throw new RuntimeException('Demo seeding requires an explicit, non-empty account configuration.');
        }

        $accounts = [];
        $emails = [];
        $passwordFingerprints = [];

        foreach ($configured as $index => $account) {
            if (! is_array($account)) {
                throw new RuntimeException("Demo account {$index} is not a valid configuration object.");
            }

            $name = trim((string) ($account['name'] ?? ''));
            $institutionKey = trim((string) ($account['institution_key'] ?? ''));
            $institution = trim((string) ($account['legacy_institution'] ?? ''));
            $email = User::canonicalEmail($account['email'] ?? null);
            $password = $account['password'] ?? null;
            $role = UserRole::tryFrom((string) ($account['role'] ?? ''));

            if ($name === ''
                || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $institutionKey) !== 1
                || $institution === ''
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || $role === null) {
                throw new RuntimeException("Demo account {$index} has incomplete or invalid non-secret fields.");
            }

            if (! is_string($password) || strlen($password) < 24) {
                throw new RuntimeException("Demo account {$index} requires a per-run secret of at least 24 characters.");
            }

            if (isset($emails[$email])) {
                throw new RuntimeException('Demo account email addresses must be unique after canonicalization.');
            }

            $passwordFingerprint = hash('sha256', $password);
            if (isset($passwordFingerprints[$passwordFingerprint])) {
                throw new RuntimeException('Every demo account requires a separate per-run secret.');
            }

            $emails[$email] = true;
            $passwordFingerprints[$passwordFingerprint] = true;
            $accounts[] = [
                'name' => $name,
                'institution_key' => $institutionKey,
                'legacy_institution' => $institution,
                'email' => $email,
                'password' => $password,
                'role' => $role,
            ];
        }

        return $accounts;
    }
}
