<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinalizeIdentityMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_exact_confirmation_and_database_sessions(): void
    {
        $this->insertSession('retained-session');

        $this->artisan('hospitrainity:finalize-identity-migration')->assertFailed();
        $this->assertDatabaseHas('sessions', ['id' => 'retained-session']);

        config()->set('session.driver', 'array');
        $this->artisan('hospitrainity:finalize-identity-migration', [
            '--confirm' => 'REVOKE-ALL-DATABASE-SESSIONS',
        ])->assertFailed();
        $this->assertDatabaseHas('sessions', ['id' => 'retained-session']);
        $this->assertDatabaseMissing('identity_audits', ['event' => 'identity_migration.sessions_revoked']);
    }

    public function test_confirmed_command_revokes_sessions_atomically_and_is_idempotent(): void
    {
        config()->set('session.driver', 'database');
        $this->insertSession('first-session');
        $this->insertSession('second-session');

        $arguments = ['--confirm' => 'REVOKE-ALL-DATABASE-SESSIONS'];
        $this->artisan('hospitrainity:finalize-identity-migration', $arguments)->assertSuccessful();

        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseHas('identity_migration_states', [
            'name' => 'normalized-institutions-session-revocation-v1',
            'revoked_session_count' => 2,
            'completion_method' => 'confirmed_console_command',
        ]);
        $this->assertNotNull(DB::table('identity_migration_states')->value('completed_at'));
        $this->assertDatabaseHas('identity_audits', [
            'event' => 'identity_migration.sessions_revoked',
            'metadata' => json_encode(['sessions_revoked' => 2]),
        ]);

        $this->insertSession('post-finalization-session');
        $this->artisan('hospitrainity:finalize-identity-migration', $arguments)->assertSuccessful();
        $this->assertDatabaseHas('sessions', ['id' => 'post-finalization-session']);
        $this->assertDatabaseCount('identity_audits', 1);
    }

    public function test_audit_failure_rolls_back_session_deletion_and_finalization_state(): void
    {
        config()->set('session.driver', 'database');
        $this->insertSession('must-survive-rollback');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_identity_finalization_audit
            BEFORE INSERT ON identity_audits
            WHEN NEW.event = 'identity_migration.sessions_revoked'
            BEGIN
                SELECT RAISE(ABORT, 'test double: identity audit unavailable');
            END
        SQL);

        try {
            $this->artisan('hospitrainity:finalize-identity-migration', [
                '--confirm' => 'REVOKE-ALL-DATABASE-SESSIONS',
            ])->assertFailed();
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_identity_finalization_audit');
        }

        $this->assertDatabaseHas('sessions', ['id' => 'must-survive-rollback']);
        $state = DB::table('identity_migration_states')
            ->where('name', 'normalized-institutions-session-revocation-v1')
            ->first();
        $this->assertNull($state->completed_at);
        $this->assertSame(0, (int) $state->revoked_session_count);
        $this->assertNull($state->completion_method);
        $this->assertDatabaseMissing('identity_audits', [
            'event' => 'identity_migration.sessions_revoked',
        ]);
    }

    private function insertSession(string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test fixture',
            'payload' => 'test fixture',
            'last_activity' => time(),
        ]);
    }
}
