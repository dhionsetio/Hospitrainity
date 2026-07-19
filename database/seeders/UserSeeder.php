<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the three demo accounts (superadmin / supervisor / user).
 *
 * Phase 8 change: this seeder used to run MySQL-only statements
 * ("SET FOREIGN_KEY_CHECKS=0" + User::truncate()) which THROW on SQLite and
 * therefore broke `php artisan db:seed` under the test suite / CI. It is now
 * idempotent and database-agnostic: updateOrCreate keyed on the unique email
 * means re-running refreshes the demo accounts in place without truncating
 * (which would also have wiped any completions FK-referencing these users).
 *
 * The demo accounts are pre-verified (email_verified_at = now()) so the
 * "verified" middleware added in Phase 1 does not lock them out.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Super Admin',
                'instansi' => 'Hospitrainity HQ',
                'email' => 'superadmin@example.com',
                'role' => UserRole::Superadmin,
            ],
            [
                'name' => 'Supervisor Hotel A',
                'instansi' => 'Hotel A',
                'email' => 'supervisor@example.com',
                'role' => UserRole::Supervisor,
            ],
            [
                'name' => 'User Biasa',
                'instansi' => 'Hotel B',
                'email' => 'user@example.com',
                'role' => UserRole::Learner,
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrNew(['email' => $account['email']]);
            $user->forceFill([
                'name' => $account['name'],
                'instansi' => $account['instansi'],
                'role' => $account['role'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
