<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\LegacyInstitutionState;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityMigrationPreflightCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pre_migration_report_is_read_only_and_hides_legacy_labels_by_default(): void
    {
        $migration = require database_path('migrations/2026_07_19_000010_normalize_identity_and_invitations.php');
        $migration->down();

        try {
            $now = now();
            $testPassword = Str::password(40);
            foreach ([
                ['hq@example.com', 'Hospitrainity HQ'],
                ['polinema@example.com', 'Politeknik Negeri Malang'],
                ['hotel@example.com', 'Hotel A'],
            ] as [$email, $legacyInstitution]) {
                DB::table('users')->insert([
                    'name' => 'Migration test double',
                    'instansi' => $legacyInstitution,
                    'email' => $email,
                    'email_verified_at' => $now,
                    'role' => 'user',
                    'password' => Hash::make($testPassword),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->insertSession('preflight-retained-session');

            $exit = Artisan::call('hospitrainity:identity-migration-preflight');
            $output = Artisan::output();
            $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(0, $exit);
            $this->assertFalse($report['normalized_schema_present']);
            $this->assertSame(3, $report['users']['total']);
            $this->assertSame(2, $report['users']['exact_mapping_candidates']);
            $this->assertSame(1, $report['users']['unmatched_or_empty_candidates']);
            $this->assertSame(1, $report['sessions']['total']);
            $this->assertTrue($report['owner_gates']['all_session_revocation_permission_required']);
            $this->assertStringNotContainsString('Hotel A', $output);
            $this->assertStringNotContainsString('hotel@example.com', $output);
            $this->assertDatabaseCount('users', 3);
            $this->assertDatabaseHas('sessions', ['id' => 'preflight-retained-session']);
            $this->assertFalse(Schema::hasTable('institutions'));

            Artisan::call('hospitrainity:identity-migration-preflight', [
                '--include-legacy-values' => true,
            ]);
            $this->assertStringContainsString('Hotel A', Artisan::output());
            $this->assertStringNotContainsString('hotel@example.com', Artisan::output());
        } finally {
            $migration->up();
        }
    }

    public function test_post_migration_report_fails_when_normalized_invariants_are_corrupt(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $polinema = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $mapped = User::factory()->create([
            'instansi' => 'Hospitrainity HQ',
            'legacy_institution_state' => LegacyInstitutionState::Mapped,
        ]);
        $this->membership($mapped, $hq, true);
        $unresolved = User::factory()->create([
            'instansi' => 'Hotel A',
            'legacy_institution_state' => LegacyInstitutionState::Unresolved,
        ]);

        $this->assertSame(0, Artisan::call('hospitrainity:identity-migration-preflight'));
        $healthy = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($healthy['normalized']['all_invariants_passed']);

        $this->membership($unresolved, $hq, true);
        $this->membership($unresolved, $polinema, true);

        $this->assertSame(1, Artisan::call('hospitrainity:identity-migration-preflight'));
        $corrupt = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($corrupt['normalized']['all_invariants_passed']);
        $failed = collect($corrupt['normalized']['invariants'])
            ->where('passed', false)
            ->pluck('name')
            ->all();
        $this->assertContains('unresolved_users_have_no_active_membership', $failed);
        $this->assertContains('at_most_one_active_default_membership_per_user', $failed);
    }

    private function membership(User $user, Institution $institution, bool $default): void
    {
        InstitutionMembership::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => $default,
            'provenance' => 'preflight_test_fixture',
            'joined_at' => now(),
        ]);
    }

    private function insertSession(string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'preflight test fixture',
            'payload' => 'preflight test fixture',
            'last_activity' => time(),
        ]);
    }
}
