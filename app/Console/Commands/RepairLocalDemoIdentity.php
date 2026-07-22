<?php

namespace App\Console\Commands;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Enums\LegacyInstitutionState;
use App\Enums\UserRole;
use App\Models\IdentityAudit;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RepairLocalDemoIdentity extends Command
{
    private const CONFIRMATION = 'REPAIR-LOCAL-DEMO-IDENTITY';

    private const PROVENANCE = 'explicit_local_demo_repair';

    /** @var list<string> */
    private const ACCEPTED_FIXTURE_METHODS = [
        self::PROVENANCE,
        'disposable_demo_fixture',
    ];

    protected $signature = 'hospitrainity:repair-local-demo-identity
        {--email= : Existing configured local demo identity to repair}
        {--confirm= : Must be exactly REPAIR-LOCAL-DEMO-IDENTITY}';

    protected $description = 'Repair one existing local demo Supervisor without changing its credentials';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refusing repair outside the local or testing environment.');

            return self::FAILURE;
        }

        if (! hash_equals(self::CONFIRMATION, (string) $this->option('confirm'))) {
            $this->error('Refusing repair: pass --confirm='.self::CONFIRMATION.' after reviewing the configured identity.');

            return self::FAILURE;
        }

        $email = User::canonicalEmail($this->option('email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('A valid configured demo account email is required.');

            return self::INVALID;
        }

        try {
            $account = $this->configuredSupervisor($email);
            $result = DB::transaction(
                fn (): array => $this->repair($account),
                attempts: 3,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The local demo identity repair failed and was rolled back. Review the protected application log before retrying.');

            return self::FAILURE;
        }

        if (! $result['changed']) {
            $this->info('This local demo identity already has the configured Instructor access. No identity data or sessions were changed.');

            return self::SUCCESS;
        }

        $this->info("Local demo identity repaired. Existing credentials were preserved; {$result['sessions_revoked']} active session(s) were revoked.");

        return self::SUCCESS;
    }

    /**
     * @return array{name: string, institution_key: string, legacy_institution: string, email: string, role: UserRole}
     */
    private function configuredSupervisor(string $email): array
    {
        $matches = collect(config('identity.demo_seed.accounts', []))
            ->filter(static fn (mixed $account): bool => is_array($account)
                && User::canonicalEmail($account['email'] ?? null) === $email)
            ->values();

        if ($matches->count() !== 1) {
            throw new RuntimeException('Repair refused because the email does not identify exactly one configured demo account.');
        }

        /** @var array<string, mixed> $configured */
        $configured = $matches->first();
        $name = trim((string) ($configured['name'] ?? ''));
        $institutionKey = trim((string) ($configured['institution_key'] ?? ''));
        $legacyInstitution = trim((string) ($configured['legacy_institution'] ?? ''));
        $role = UserRole::tryFrom((string) ($configured['role'] ?? ''));

        if ($name === ''
            || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $institutionKey) !== 1
            || $legacyInstitution === ''
            || $role !== UserRole::Supervisor) {
            throw new RuntimeException('Repair supports only a complete, explicitly configured local demo Supervisor.');
        }

        return [
            'name' => $name,
            'institution_key' => $institutionKey,
            'legacy_institution' => $legacyInstitution,
            'email' => $email,
            'role' => $role,
        ];
    }

    /**
     * @param  array{name: string, institution_key: string, legacy_institution: string, email: string, role: UserRole}  $account
     * @return array{changed: bool, sessions_revoked: int}
     */
    private function repair(array $account): array
    {
        $user = User::query()
            ->where('email', $account['email'])
            ->lockForUpdate()
            ->first();
        if ($user === null) {
            throw new RuntimeException('Repair refused because the configured demo account does not exist.');
        }
        if (! $user->isSupervisor()) {
            throw new RuntimeException('Repair refused because the existing account role does not match the configured demo role.');
        }
        if (! hash_equals($account['legacy_institution'], (string) $user->instansi)) {
            throw new RuntimeException('Repair refused because the existing institution label does not match the configured demo institution.');
        }
        if (! $user->hasVerifiedEmail()) {
            throw new RuntimeException('Repair refused because the existing Supervisor email is not verified.');
        }
        if ($user->isDisabled()) {
            throw new RuntimeException('Repair refused because the existing Supervisor account is disabled.');
        }

        $changed = false;
        $authorityChanged = false;
        $institution = Institution::query()
            ->where('key', $account['institution_key'])
            ->lockForUpdate()
            ->first();

        if ($institution === null) {
            $institution = Institution::query()->create([
                'key' => $account['institution_key'],
                'name_id' => $account['legacy_institution'],
                'name_en' => $account['legacy_institution'],
                'status' => InstitutionStatus::Active,
                'verified_at' => null,
                'verification_method' => self::PROVENANCE,
            ]);
            $changed = true;
        } elseif ($institution->name_id !== $account['legacy_institution']
            || $institution->name_en !== $account['legacy_institution']
            || $institution->status !== InstitutionStatus::Active
            || ! in_array($institution->verification_method, self::ACCEPTED_FIXTURE_METHODS, true)) {
            throw new RuntimeException('Repair refused because the configured demo institution key belongs to a different or non-active institution.');
        }

        $membership = InstitutionMembership::query()
            ->where('institution_id', $institution->getKey())
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();
        if ($membership !== null && ! in_array($membership->provenance, self::ACCEPTED_FIXTURE_METHODS, true)) {
            throw new RuntimeException('Repair refused because the existing membership is not a recognized local demo fixture.');
        }

        $conflictingDefault = InstitutionMembership::query()
            ->where('user_id', $user->getKey())
            ->where('institution_id', '!=', $institution->getKey())
            ->where('status', InstitutionMembershipStatus::Active->value)
            ->where('is_default', true)
            ->lockForUpdate()
            ->exists();
        if ($conflictingDefault) {
            throw new RuntimeException('Repair refused because the account already has a different active default institution.');
        }

        if ($membership === null) {
            $membership = InstitutionMembership::query()->create([
                'institution_id' => $institution->getKey(),
                'user_id' => $user->getKey(),
                'status' => InstitutionMembershipStatus::Active,
                'is_default' => true,
                'provenance' => self::PROVENANCE,
                'joined_at' => now(),
                'revoked_at' => null,
            ]);
            $changed = true;
            $authorityChanged = true;
        } else {
            $membershipWasActive = $membership->status === InstitutionMembershipStatus::Active;
            $membership->forceFill([
                'status' => InstitutionMembershipStatus::Active,
                'is_default' => true,
                'revoked_at' => null,
            ]);
            if ($membership->isDirty()) {
                $membership->save();
                $changed = true;
                $authorityChanged = ! $membershipWasActive;
            }
        }

        $unexpectedRole = InstitutionRoleAssignment::query()
            ->where('institution_membership_id', $membership->getKey())
            ->where('role', '!=', InstitutionRole::Instructor->value)
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->exists();
        if ($unexpectedRole) {
            throw new RuntimeException('Repair refused because the membership already has an unsupported active institution role.');
        }

        $assignment = InstitutionRoleAssignment::query()
            ->where('institution_membership_id', $membership->getKey())
            ->where('role', InstitutionRole::Instructor->value)
            ->lockForUpdate()
            ->first();
        if ($assignment === null) {
            InstitutionRoleAssignment::query()->create([
                'institution_membership_id' => $membership->getKey(),
                'role' => InstitutionRole::Instructor,
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
                'revoked_at' => null,
            ]);
            $changed = true;
            $authorityChanged = true;
        } elseif ($assignment->revoked_at !== null) {
            $assignment->forceFill(['revoked_at' => null])->save();
            $changed = true;
            $authorityChanged = true;
        }

        if ($user->getRawOriginal('legacy_institution_state') !== LegacyInstitutionState::Mapped->value) {
            $user->forceFill(['legacy_institution_state' => LegacyInstitutionState::Mapped])->save();
            $changed = true;
        }

        $sessionsRevoked = 0;
        if ($authorityChanged) {
            $user->forceFill(['remember_token' => Str::random(60)])->save();
            $sessionsRevoked = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        if ($changed) {
            IdentityAudit::query()->create([
                'target_user_id' => $user->getKey(),
                'institution_id' => $institution->getKey(),
                'event' => 'identity.local_demo_repaired',
                'metadata' => [
                    'role' => InstitutionRole::Instructor->value,
                    'provenance' => self::PROVENANCE,
                    'authority_changed' => $authorityChanged,
                    'sessions_revoked' => $sessionsRevoked,
                ],
                'created_at' => now(),
            ]);
        }

        return ['changed' => $changed, 'sessions_revoked' => $sessionsRevoked];
    }
}
