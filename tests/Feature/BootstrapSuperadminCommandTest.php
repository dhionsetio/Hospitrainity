<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class BootstrapSuperadminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_explicit_confirmation_and_writes_nothing_without_it(): void
    {
        $this->artisan('hospitrainity:bootstrap-superadmin', [
            'email' => 'owner@example.com',
            'name' => 'Product Owner',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('identity_audits', 0);
    }

    public function test_command_creates_exactly_one_unverified_superadmin_without_a_default_password(): void
    {
        Notification::fake();
        $this->markIdentityMigrationFinalized();

        $this->artisan('hospitrainity:bootstrap-superadmin', [
            'email' => 'Owner@Example.com',
            'name' => 'Product Owner',
            '--confirm' => 'BOOTSTRAP-INITIAL-SUPERADMIN',
        ])->assertSuccessful();

        $user = User::query()->sole();
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $this->assertSame('owner@example.com', $user->email);
        $this->assertSame(UserRole::Superadmin, $user->role);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertSame($user->id, $hq->fresh()->owner_user_id);
        $this->assertNull($hq->fresh()->verified_at);
        $this->assertNull($hq->fresh()->verification_method);
        $this->assertDatabaseHas('institution_memberships', [
            'institution_id' => $hq->id,
            'user_id' => $user->id,
            'is_default' => true,
            'provenance' => 'initial_superadmin_bootstrap',
        ]);
        $this->assertDatabaseHas('identity_audits', [
            'target_user_id' => $user->id,
            'event' => 'superadmin.bootstrap_created',
        ]);
        $this->assertDatabaseHas('identity_bootstrap_locks', [
            'name' => 'initial-superadmin',
            'completed_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_command_refuses_every_rerun_and_does_not_replace_existing_superadmin(): void
    {
        Notification::fake();
        $this->markIdentityMigrationFinalized();
        $arguments = [
            'email' => 'owner@example.com',
            'name' => 'Product Owner',
            '--confirm' => 'BOOTSTRAP-INITIAL-SUPERADMIN',
        ];

        $this->artisan('hospitrainity:bootstrap-superadmin', $arguments)->assertSuccessful();
        $firstUserId = User::query()->sole()->id;

        $this->artisan('hospitrainity:bootstrap-superadmin', [
            'email' => 'second-owner@example.com',
            'name' => 'Second Owner',
            '--confirm' => 'BOOTSTRAP-INITIAL-SUPERADMIN',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($firstUserId, User::query()->sole()->id);
    }

    public function test_command_refuses_until_the_explicit_session_revocation_step_is_complete(): void
    {
        Notification::fake();

        $this->artisan('hospitrainity:bootstrap-superadmin', [
            'email' => 'owner@example.com',
            'name' => 'Product Owner',
            '--confirm' => 'BOOTSTRAP-INITIAL-SUPERADMIN',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_notification_failure_preserves_one_time_account_and_requires_normal_reset_recovery(): void
    {
        $this->markIdentityMigrationFinalized();
        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('test double: mail transport unavailable'));
        $arguments = [
            'email' => 'owner@example.com',
            'name' => 'Product Owner',
            '--confirm' => 'BOOTSTRAP-INITIAL-SUPERADMIN',
        ];

        $this->artisan('hospitrainity:bootstrap-superadmin', $arguments)->assertFailed();

        $user = User::query()->sole();
        $this->assertSame(UserRole::Superadmin, $user->role);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertDatabaseHas('identity_bootstrap_locks', [
            'name' => 'initial-superadmin',
            'completed_user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('identity_audits', [
            'target_user_id' => $user->id,
            'event' => 'superadmin.bootstrap_notification_failed',
        ]);

        Notification::shouldReceive('send')->never();
        $this->artisan('hospitrainity:bootstrap-superadmin', $arguments)->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    private function markIdentityMigrationFinalized(): void
    {
        DB::table('identity_migration_states')
            ->where('name', 'normalized-institutions-session-revocation-v1')
            ->update([
                'completed_at' => now(),
                'completion_method' => 'test_fixture',
                'updated_at' => now(),
            ]);
    }
}
