<?php

namespace Tests\Feature;

use App\Enums\LegacyInstitutionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_expand_contract_migration_uses_only_reviewed_exact_mappings_and_preserves_sessions_for_explicit_revocation(): void
    {
        $migration = require database_path('migrations/2026_07_19_000010_normalize_identity_and_invitations.php');
        $migration->down();

        $now = now();
        $testPassword = Str::password(40);
        $ids = [];
        foreach ([
            ['hq@example.com', 'Hospitrainity HQ', 'superadmin'],
            ['polinema-id@example.com', 'Politeknik Negeri Malang', 'user'],
            ['polinema-en@example.com', 'State Polytechnic of Malang', 'user'],
            ['hotel@example.com', 'Hotel A', 'supervisor'],
        ] as [$email, $legacyInstitution, $role]) {
            $ids[$email] = DB::table('users')->insertGetId([
                'name' => $email,
                'instansi' => $legacyInstitution,
                'email' => $email,
                'email_verified_at' => $now,
                'role' => $role,
                'password' => Hash::make($testPassword),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('sessions')->insert([
            'id' => 'pre-migration-session',
            'user_id' => $ids['hq@example.com'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test fixture',
            'payload' => 'test fixture',
            'last_activity' => time(),
        ]);

        $migration->up();

        $this->assertDatabaseCount('institutions', 2);
        $this->assertDatabaseHas('institutions', [
            'key' => 'politeknik-negeri-malang',
            'name_id' => 'Politeknik Negeri Malang',
            'name_en' => 'State Polytechnic of Malang',
            'verified_at' => null,
            'verification_method' => null,
        ]);
        $this->assertDatabaseMissing('institutions', ['name_en' => 'Hotel A']);
        $this->assertDatabaseCount('institution_memberships', 3);
        foreach (['hq@example.com', 'polinema-id@example.com', 'polinema-en@example.com'] as $email) {
            $this->assertDatabaseHas('users', [
                'email' => $email,
                'legacy_institution_state' => LegacyInstitutionState::Mapped->value,
            ]);
        }
        $this->assertDatabaseHas('users', [
            'email' => 'hotel@example.com',
            'instansi' => 'Hotel A',
            'legacy_institution_state' => LegacyInstitutionState::Unresolved->value,
        ]);
        $this->assertDatabaseMissing('institution_memberships', ['user_id' => $ids['hotel@example.com']]);
        $this->assertDatabaseHas('sessions', ['id' => 'pre-migration-session']);
        $this->assertDatabaseHas('identity_migration_states', [
            'name' => 'normalized-institutions-session-revocation-v1',
            'completed_at' => null,
            'revoked_session_count' => 0,
        ]);
        $this->assertDatabaseCount('users', 4);

        $migration->down();
        $this->assertFalse(Schema::hasTable('institutions'));
        $this->assertFalse(Schema::hasTable('identity_migration_states'));
        $this->assertFalse(Schema::hasColumn('users', 'legacy_institution_state'));
        $this->assertSame('Hotel A', DB::table('users')->where('id', $ids['hotel@example.com'])->value('instansi'));
        $this->assertDatabaseCount('users', 4);

        // Restore the migrated schema so RefreshDatabase teardown sees the same
        // shape recorded in the migration repository.
        $migration->up();
    }
}
