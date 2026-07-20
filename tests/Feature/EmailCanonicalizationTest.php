<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\User;
use App\Services\InstitutionInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class EmailCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_storage_and_login_use_trimmed_lowercase_email(): void
    {
        $user = User::factory()->create([
            'email' => '  Mixed.Case@Example.COM  ',
            'password' => 'password',
        ]);

        $this->assertSame('mixed.case@example.com', $user->fresh()->email);

        $this->post('/login', [
            'email' => '  MIXED.CASE@EXAMPLE.COM  ',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invitation_redemption_does_not_duplicate_a_case_variant_of_an_existing_email(): void
    {
        $institution = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $existing = User::factory()->create([
            'email' => 'learner@example.com',
            'instansi' => $institution->name_id,
        ]);
        $issuer = User::factory()->create(['role' => UserRole::Supervisor]);
        InstitutionMembership::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $issuer->id,
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'test_fixture',
            'joined_at' => now(),
        ]);
        $issued = app(InstitutionInvitationService::class)->issue(
            $issuer,
            $institution,
            'LEARNER@EXAMPLE.COM',
        );

        $this->post(route('invitations.redeem', ['token' => $issued['token']]), [
            'name' => 'Duplicate Learner',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ])->assertRedirect(route('invitations.unavailable'));

        $this->assertSame(1, User::where('email', 'learner@example.com')->count());
    }

    public function test_password_reset_accepts_a_case_variant_of_the_account_email(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'RESET@EXAMPLE.COM',
            'password' => 'Replacement123!',
            'password_confirmation' => 'Replacement123!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Replacement123!', $user->fresh()->password));
    }

    public function test_migration_normalizes_users_and_reset_tokens_idempotently(): void
    {
        $user = User::factory()->create(['email' => 'source@example.com']);
        DB::table('users')->where('id', $user->id)->update(['email' => ' Source@Example.COM ']);
        DB::table('password_reset_tokens')->insert([
            'email' => ' Source@Example.COM ',
            'token' => 'test-token',
            'created_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_07_16_000004_normalize_email_addresses.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'source@example.com']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'source@example.com']);
    }

    public function test_migration_reports_canonical_collisions_before_mutating_any_row(): void
    {
        $first = User::factory()->create(['email' => 'first@example.com']);
        $second = User::factory()->create(['email' => 'second@example.com']);
        DB::table('users')->where('id', $first->id)->update(['email' => 'Case@Example.com']);
        DB::table('users')->where('id', $second->id)->update(['email' => 'case@example.com']);

        $migration = require database_path('migrations/2026_07_16_000004_normalize_email_addresses.php');

        try {
            $migration->up();
            $this->fail('The migration did not report the canonical collision.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString("[{$first->id},{$second->id}]", $exception->getMessage());
            $this->assertStringContainsString('aborted without changes', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $first->id, 'email' => 'Case@Example.com']);
        $this->assertDatabaseHas('users', ['id' => $second->id, 'email' => 'case@example.com']);
    }
}
