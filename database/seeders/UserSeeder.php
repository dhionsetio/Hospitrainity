<?php

namespace Database\Seeders;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Enums\LegacyInstitutionState;
use App\Enums\PlatformRole;
use App\Enums\UserCapability;
use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Models\UserCapabilityAssignment;
use App\Services\DemoSeedGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Seeds disposable identities only after DemoSeedGuard authorizes the runtime.
 * Passwords are supplied by the local/E2E process and are never literals or
 * console output. Re-running is idempotent and does not truncate user data.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = app(DemoSeedGuard::class)->authorizedAccounts();

        DB::transaction(function () use ($accounts): void {
            $this->assertNoIdentityCollisions($accounts);

            foreach ($accounts as $account) {
                $institution = Institution::query()->firstOrCreate(
                    ['key' => $account['institution_key']],
                    [
                        'name_id' => $account['legacy_institution'],
                        'name_en' => $account['legacy_institution'],
                        'status' => InstitutionStatus::Active,
                        'verified_at' => now(),
                        'verification_method' => 'disposable_demo_fixture',
                    ],
                );
                $user = User::firstOrNew(['email' => $account['email']]);
                $user->forceFill([
                    'name' => $account['name'],
                    'instansi' => $account['legacy_institution'],
                    'role' => $account['role'],
                    'password' => Hash::make($account['password']),
                    'email_verified_at' => now(),
                    'legacy_institution_state' => LegacyInstitutionState::Mapped,
                ])->save();
                $membership = InstitutionMembership::query()->firstOrNew([
                    'institution_id' => $institution->getKey(),
                    'user_id' => $user->getKey(),
                ]);
                $membership->fill([
                    'status' => InstitutionMembershipStatus::Active,
                    'is_default' => true,
                    'revoked_at' => null,
                ]);
                if (! $membership->exists) {
                    $membership->fill([
                        'provenance' => 'disposable_demo_fixture',
                        'joined_at' => now(),
                    ]);
                }
                $membership->save();

                $institutionRole = match ($account['role']) {
                    UserRole::Superadmin->value, UserRole::Superadmin => InstitutionRole::InstitutionAdmin,
                    UserRole::Supervisor->value, UserRole::Supervisor,
                    UserRole::Admin->value, UserRole::Admin => InstitutionRole::Instructor,
                    default => InstitutionRole::Learner,
                };
                InstitutionRoleAssignment::query()->firstOrCreate([
                    'institution_membership_id' => $membership->getKey(),
                    'role' => $institutionRole->value,
                ], [
                    'assigned_by_user_id' => null,
                    'assigned_at' => now(),
                ])->forceFill(['revoked_at' => null])->save();

                if ($account['role'] === UserRole::Superadmin->value || $account['role'] === UserRole::Superadmin) {
                    PlatformRoleAssignment::query()->firstOrCreate([
                        'user_id' => $user->getKey(),
                        'role' => PlatformRole::SystemAdmin->value,
                    ], ['assigned_by_user_id' => null, 'assigned_at' => now()])
                        ->forceFill(['revoked_at' => null])->save();
                }
                if ($account['role'] === UserRole::Admin->value || $account['role'] === UserRole::Admin) {
                    UserCapabilityAssignment::query()->firstOrCreate([
                        'user_id' => $user->getKey(),
                        'capability' => UserCapability::ContentAuthor->value,
                    ], ['assigned_by_user_id' => null, 'assigned_at' => now()])
                        ->forceFill(['revoked_at' => null])->save();
                }
            }
        }, 3);
    }

    /**
     * Idempotent reruns may update only identities already proven to be demo
     * fixtures. A configured email or institution key must never take over an
     * unrelated local account or organization.
     *
     * @param  list<array{name: string, institution_key: string, legacy_institution: string, email: string, password: string, role: mixed}>  $accounts
     */
    private function assertNoIdentityCollisions(array $accounts): void
    {
        foreach ($accounts as $account) {
            $existingUser = User::query()->where('email', $account['email'])->first();
            if ($existingUser !== null
                && ! InstitutionMembership::query()
                    ->where('user_id', $existingUser->getKey())
                    ->where('provenance', 'disposable_demo_fixture')
                    ->whereHas('institution', fn ($query) => $query->where('key', $account['institution_key']))
                    ->exists()) {
                throw new RuntimeException('Demo seeding refused to overwrite an existing non-demo account.');
            }

            $existingInstitution = Institution::query()->where('key', $account['institution_key'])->first();
            if ($existingInstitution !== null
                && $existingInstitution->key !== 'hospitrainity-hq'
                && $existingInstitution->verification_method !== 'disposable_demo_fixture') {
                throw new RuntimeException('Demo seeding refused to reuse an existing non-demo institution.');
            }
        }
    }
}
