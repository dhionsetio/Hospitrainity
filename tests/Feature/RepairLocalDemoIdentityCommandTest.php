<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RepairLocalDemoIdentityCommandTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'hospitrainity:repair-local-demo-identity';

    private const CONFIRMATION = 'REPAIR-LOCAL-DEMO-IDENTITY';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'local';
        config()->set('identity.demo_seed.accounts', [[
            'name' => 'Configured Demo Supervisor',
            'institution_key' => 'demo-hotel-a',
            'legacy_institution' => 'Hotel A',
            'email' => 'supervisor@example.com',
            'password' => null,
            'role' => UserRole::Supervisor->value,
        ]]);
    }

    public function test_repair_grants_only_instructor_and_preserves_the_existing_identity_and_credentials(): void
    {
        $user = $this->supervisor();
        $original = $user->only(['name', 'email', 'password']);
        $originalRememberToken = $user->remember_token;
        $this->insertSession('session-to-revoke', $user);

        $this->artisan(self::COMMAND, $this->arguments())
            ->expectsOutput('Local demo identity repaired. Existing credentials were preserved; 1 active session(s) were revoked.')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame($original['name'], $user->name);
        $this->assertSame($original['email'], $user->email);
        $this->assertSame($original['password'], $user->password);
        $this->assertNotSame($originalRememberToken, $user->remember_token);
        $this->assertSame(LegacyInstitutionState::Mapped, $user->legacy_institution_state);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-to-revoke']);

        $institution = Institution::query()->where('key', 'demo-hotel-a')->sole();
        $this->assertSame('Hotel A', $institution->name_id);
        $this->assertSame('Hotel A', $institution->name_en);
        $this->assertSame(InstitutionStatus::Active, $institution->status);
        $this->assertNull($institution->verified_at);
        $this->assertSame('explicit_local_demo_repair', $institution->verification_method);

        $membership = InstitutionMembership::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($institution)
            ->sole();
        $this->assertSame(InstitutionMembershipStatus::Active, $membership->status);
        $this->assertTrue($membership->is_default);
        $this->assertSame('explicit_local_demo_repair', $membership->provenance);
        $this->assertDatabaseHas('institution_role_assignments', [
            'institution_membership_id' => $membership->getKey(),
            'role' => InstitutionRole::Instructor->value,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseMissing('institution_role_assignments', [
            'institution_membership_id' => $membership->getKey(),
            'role' => InstitutionRole::InstitutionAdmin->value,
        ]);
        $this->assertDatabaseCount('platform_role_assignments', 0);
        $this->assertDatabaseCount('user_capability_assignments', 0);

        $audit = IdentityAudit::query()->where('event', 'identity.local_demo_repaired')->sole();
        $this->assertSame($user->getKey(), $audit->target_user_id);
        $this->assertSame($institution->getKey(), $audit->institution_id);
        $this->assertSame([
            'role' => InstitutionRole::Instructor->value,
            'provenance' => 'explicit_local_demo_repair',
            'authority_changed' => true,
            'sessions_revoked' => 1,
        ], $audit->metadata);
    }

    public function test_repair_is_idempotent_and_does_not_revoke_a_session_when_authority_is_unchanged(): void
    {
        $user = $this->supervisor();
        $this->artisan(self::COMMAND, $this->arguments())->assertSuccessful();
        $user->refresh();
        $rememberToken = $user->remember_token;
        $counts = [
            'institutions' => Institution::query()->count(),
            'memberships' => InstitutionMembership::query()->count(),
            'assignments' => InstitutionRoleAssignment::query()->count(),
            'audits' => IdentityAudit::query()->count(),
        ];
        $this->insertSession('session-after-repair', $user);

        $this->artisan(self::COMMAND, $this->arguments())
            ->expectsOutput('This local demo identity already has the configured Instructor access. No identity data or sessions were changed.')
            ->assertSuccessful();

        $this->assertDatabaseHas('sessions', ['id' => 'session-after-repair', 'user_id' => $user->getKey()]);
        $this->assertSame($rememberToken, $user->fresh()->remember_token);
        $this->assertSame($counts['institutions'], Institution::query()->count());
        $this->assertSame($counts['memberships'], InstitutionMembership::query()->count());
        $this->assertSame($counts['assignments'], InstitutionRoleAssignment::query()->count());
        $this->assertSame($counts['audits'], IdentityAudit::query()->count());
    }

    public function test_audit_failure_rolls_back_the_entire_repair_and_preserves_the_existing_session(): void
    {
        $user = $this->supervisor();
        $rememberToken = $user->remember_token;
        $this->insertSession('session-that-must-survive', $user);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_local_demo_repair_audit
            BEFORE INSERT ON identity_audits
            WHEN NEW.event = 'identity.local_demo_repaired'
            BEGIN
                SELECT RAISE(ABORT, 'test double: identity audit unavailable');
            END
        SQL);

        try {
            $this->artisan(self::COMMAND, $this->arguments())->assertFailed();
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_local_demo_repair_audit');
        }

        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-a']);
        $this->assertDatabaseMissing('institution_memberships', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('institution_role_assignments', ['role' => InstitutionRole::Instructor->value]);
        $this->assertDatabaseHas('sessions', ['id' => 'session-that-must-survive', 'user_id' => $user->getKey()]);
        $user->refresh();
        $this->assertSame($rememberToken, $user->remember_token);
        $this->assertSame(LegacyInstitutionState::Unresolved, $user->legacy_institution_state);
    }

    public function test_production_repair_fails_before_any_write(): void
    {
        $user = $this->supervisor();
        $this->app['env'] = 'production';

        $this->artisan(self::COMMAND, $this->arguments())
            ->expectsOutput('Refusing repair outside the local or testing environment.')
            ->assertFailed();

        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-a']);
        $this->assertDatabaseMissing('institution_memberships', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('identity_audits', ['event' => 'identity.local_demo_repaired']);
        $this->assertSame(LegacyInstitutionState::Unresolved, $user->fresh()->legacy_institution_state);
    }

    public function test_repair_requires_the_exact_confirmation_before_any_write(): void
    {
        $user = $this->supervisor();

        $this->artisan(self::COMMAND, [
            '--email' => $user->email,
            '--confirm' => 'repair',
        ])->assertFailed();

        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-a']);
        $this->assertDatabaseMissing('institution_memberships', ['user_id' => $user->getKey()]);
    }

    public function test_repair_rejects_an_email_that_is_not_in_the_demo_configuration(): void
    {
        $user = $this->supervisor(['email' => 'other-supervisor@example.com']);

        $this->artisan(self::COMMAND, [
            '--email' => $user->email,
            '--confirm' => self::CONFIRMATION,
        ])->assertFailed();

        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-a']);
        $this->assertDatabaseMissing('institution_memberships', ['user_id' => $user->getKey()]);
    }

    public function test_repair_never_creates_a_missing_account(): void
    {
        $this->artisan(self::COMMAND, $this->arguments())->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'supervisor@example.com']);
        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-a']);
    }

    public function test_repair_rejects_a_legacy_role_mismatch(): void
    {
        $user = $this->supervisor(['role' => UserRole::Learner]);

        $this->artisan(self::COMMAND, $this->arguments())->assertFailed();

        $this->assertDatabaseMissing('institution_memberships', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-a']);
    }

    public function test_repair_rejects_a_legacy_institution_label_mismatch(): void
    {
        $user = $this->supervisor(['instansi' => 'Different Hotel']);

        $this->artisan(self::COMMAND, $this->arguments())->assertFailed();

        $this->assertDatabaseMissing('institution_memberships', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-a']);
    }

    public function test_repair_rejects_an_institution_key_collision(): void
    {
        $user = $this->supervisor();
        Institution::query()->create([
            'key' => 'demo-hotel-a',
            'name_id' => 'Unrelated institution',
            'name_en' => 'Unrelated institution',
            'status' => InstitutionStatus::Active,
            'verified_at' => null,
            'verification_method' => null,
        ]);

        $this->artisan(self::COMMAND, $this->arguments())->assertFailed();

        $this->assertDatabaseMissing('institution_memberships', ['user_id' => $user->getKey()]);
        $this->assertDatabaseMissing('identity_audits', ['event' => 'identity.local_demo_repaired']);
    }

    public function test_repair_rejects_an_existing_non_fixture_membership(): void
    {
        $user = $this->supervisor();
        $institution = $this->fixtureInstitution();
        InstitutionMembership::query()->create([
            'institution_id' => $institution->getKey(),
            'user_id' => $user->getKey(),
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'institution_invitation',
            'joined_at' => now(),
        ]);

        $this->artisan(self::COMMAND, $this->arguments())->assertFailed();

        $this->assertDatabaseMissing('institution_role_assignments', ['role' => InstitutionRole::Instructor->value]);
        $this->assertSame(LegacyInstitutionState::Unresolved, $user->fresh()->legacy_institution_state);
        $this->assertDatabaseMissing('identity_audits', ['event' => 'identity.local_demo_repaired']);
    }

    /** @param array<string, mixed> $attributes */
    private function supervisor(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Existing Supervisor',
            'email' => 'supervisor@example.com',
            'role' => UserRole::Supervisor,
            'instansi' => 'Hotel A',
            'legacy_institution_state' => LegacyInstitutionState::Unresolved,
        ], $attributes));
    }

    private function fixtureInstitution(): Institution
    {
        return Institution::query()->create([
            'key' => 'demo-hotel-a',
            'name_id' => 'Hotel A',
            'name_en' => 'Hotel A',
            'status' => InstitutionStatus::Active,
            'verified_at' => null,
            'verification_method' => 'explicit_local_demo_repair',
        ]);
    }

    /** @return array{--email: string, --confirm: string} */
    private function arguments(): array
    {
        return [
            '--email' => 'supervisor@example.com',
            '--confirm' => self::CONFIRMATION,
        ];
    }

    private function insertSession(string $id, User $user): void
    {
        DB::table((string) config('session.table', 'sessions'))->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'local demo repair test fixture',
            'payload' => 'local demo repair test fixture',
            'last_activity' => time(),
        ]);
    }
}
