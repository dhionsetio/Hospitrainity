<?php

namespace App\Console\Commands;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Enums\LegacyInstitutionState;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\IdentityAudit;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BootstrapSuperadmin extends Command
{
    protected $signature = 'hospitrainity:bootstrap-superadmin
        {email : Mailbox controlled by the initial Superadmin}
        {name : Display name for the initial Superadmin}
        {--institution=hospitrainity-hq : Initial active institution key}
        {--confirm= : Must be exactly BOOTSTRAP-INITIAL-SUPERADMIN}';

    protected $description = 'Create the first Superadmin and send a one-time password setup link';

    public function handle(): int
    {
        if (! hash_equals('BOOTSTRAP-INITIAL-SUPERADMIN', (string) $this->option('confirm'))) {
            $this->error('Refusing bootstrap: pass --confirm=BOOTSTRAP-INITIAL-SUPERADMIN after verifying the target mailbox.');

            return self::FAILURE;
        }

        $email = User::canonicalEmail($this->argument('email'));
        $name = trim((string) $this->argument('name'));
        $institutionKey = trim((string) $this->option('institution'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $name === '' || mb_strlen($name) > 255) {
            $this->error('A valid email address and a display name of 1–255 characters are required.');

            return self::INVALID;
        }

        try {
            $user = DB::transaction(function () use ($email, $name, $institutionKey): User {
                $lock = DB::table('identity_bootstrap_locks')
                    ->where('name', 'initial-superadmin')
                    ->lockForUpdate()
                    ->first();
                if ($lock === null) {
                    throw new RuntimeException('The identity bootstrap lock is missing. Run migrations first.');
                }

                $identityMigration = DB::table('identity_migration_states')
                    ->where('name', 'normalized-institutions-session-revocation-v1')
                    ->lockForUpdate()
                    ->first();
                if ($identityMigration === null || $identityMigration->completed_at === null) {
                    throw new RuntimeException('Bootstrap refused until the confirmed identity session-revocation step is complete.');
                }

                if ($lock->completed_at !== null
                    || User::query()
                        ->where('role', UserRole::Superadmin->value)
                        ->whereNull('disabled_at')
                        ->lockForUpdate()
                        ->exists()) {
                    throw new RuntimeException('Bootstrap refused because a Superadmin already exists or the one-time bootstrap was completed.');
                }

                if (User::query()->where('email', $email)->lockForUpdate()->exists()) {
                    throw new RuntimeException('Bootstrap refused because the target email already belongs to an account.');
                }

                $institution = Institution::query()
                    ->where('key', $institutionKey)
                    ->where('status', InstitutionStatus::Active->value)
                    ->lockForUpdate()
                    ->first();
                if ($institution === null) {
                    throw new RuntimeException('Bootstrap refused because the requested institution is not active.');
                }

                $user = User::query()->create([
                    'name' => $name,
                    'instansi' => $institution->name_id,
                    'email' => $email,
                    // Unrecoverable random material prevents password login until
                    // the mailbox owner completes the password-reset workflow.
                    'password' => Str::password(64),
                ]);
                $user->forceFill([
                    'role' => UserRole::Superadmin,
                    'legacy_institution_state' => LegacyInstitutionState::Mapped,
                    'email_verified_at' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                $membership = InstitutionMembership::query()->create([
                    'institution_id' => $institution->getKey(),
                    'user_id' => $user->getKey(),
                    'status' => InstitutionMembershipStatus::Active,
                    'is_default' => true,
                    'provenance' => 'initial_superadmin_bootstrap',
                    'joined_at' => now(),
                ]);
                InstitutionRoleAssignment::query()->create([
                    'institution_membership_id' => $membership->getKey(),
                    'role' => InstitutionRole::InstitutionAdmin,
                    'assigned_by_user_id' => null,
                    'assigned_at' => now(),
                ]);
                PlatformRoleAssignment::query()->create([
                    'user_id' => $user->getKey(),
                    'role' => PlatformRole::SystemAdmin,
                    'assigned_by_user_id' => null,
                    'assigned_at' => now(),
                ]);

                if ($institution->key === 'hospitrainity-hq' && $institution->owner_user_id === null) {
                    // Ownership follows the accepted creator decision, while
                    // institutional/domain verification remains a later named
                    // workflow and must not be inferred from CLI execution.
                    $institution->forceFill(['owner_user_id' => $user->getKey()])->save();
                }

                IdentityAudit::query()->create([
                    'target_user_id' => $user->getKey(),
                    'institution_id' => $institution->getKey(),
                    'event' => 'superadmin.bootstrap_created',
                    'metadata' => ['email_verified' => false, 'password_setup' => 'out_of_band_reset'],
                    'created_at' => now(),
                ]);

                DB::table('identity_bootstrap_locks')
                    ->where('name', 'initial-superadmin')
                    ->update([
                        'completed_user_id' => $user->getKey(),
                        'completed_at' => now(),
                        'updated_at' => now(),
                    ]);

                return $user;
            }, 3);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token);
            unset($token);
        } catch (Throwable) {
            IdentityAudit::query()->create([
                'target_user_id' => $user->getKey(),
                'event' => 'superadmin.bootstrap_notification_failed',
                'metadata' => ['recovery' => 'normal_password_reset_after_mail_repair'],
                'created_at' => now(),
            ]);
            $this->error('The account was created, but the setup email failed. Repair mail delivery, then use the normal password-reset request; do not rerun bootstrap.');

            return self::FAILURE;
        }

        $this->info('Initial Superadmin created. A time-limited password setup link was sent to the target mailbox.');
        $this->warn('The account remains email-unverified until the owner signs in and completes the normal verification flow.');

        return self::SUCCESS;
    }
}
