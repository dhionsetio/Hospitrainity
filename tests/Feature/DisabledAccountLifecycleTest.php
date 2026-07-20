<?php

namespace Tests\Feature;

use App\Enums\AccountDisableReason;
use App\Enums\UserRole;
use App\Models\IdentityAudit;
use App\Models\User;
use App\Services\AccountLifecycleService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DisabledAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    public function test_disabling_is_audited_and_revokes_only_the_target_sessions(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create(['remember_token' => 'before-disable']);
        $other = User::factory()->create();
        $this->sessionFor($target, 'target-session');
        $this->sessionFor($other, 'other-session');

        $result = app(AccountLifecycleService::class)
            ->disable($target, $actor, AccountDisableReason::TestFixture);

        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['sessions_revoked']);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $this->assertTrue($target->fresh()->isDisabled());
        $this->assertNotSame('before-disable', $target->fresh()->remember_token);
        $this->assertDatabaseHas('identity_audits', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'event' => 'account.disabled',
        ]);
        $this->assertSame(
            AccountDisableReason::TestFixture->value,
            IdentityAudit::query()->where('event', 'account.disabled')->firstOrFail()->metadata['reason_code'],
        );
    }

    public function test_disabled_account_cannot_login_or_receive_or_use_a_reset_link(): void
    {
        Notification::fake();
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('correct-password'),
        ]);
        app(AccountLifecycleService::class)
            ->disable($target, $actor, AccountDisableReason::OwnerRequest);

        $this->post(route('login'), [
            'email' => 'disabled@example.com',
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('password.email'), ['email' => 'disabled@example.com'])
            ->assertRedirect();
        Notification::assertNotSentTo($target, ResetPassword::class);
    }

    public function test_a_reset_token_issued_before_disabling_cannot_reset_the_account(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create([
            'email' => 'disabled-token@example.com',
            'password' => Hash::make('original-password'),
        ]);
        $token = Password::createToken($target);
        app(AccountLifecycleService::class)
            ->disable($target, $actor, AccountDisableReason::SecurityHold);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $target->email,
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('original-password', $target->fresh()->password));
    }

    public function test_an_authenticated_disabled_account_is_logged_out_on_its_next_request(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create();
        app(AccountLifecycleService::class)
            ->disable($target, $actor, AccountDisableReason::SecurityHold);

        $this->actingAs($target->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertHeader('X-Account-State', 'unavailable');
        $this->assertGuest();
    }

    public function test_reenabling_is_audited_and_restores_authentication_eligibility(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create([
            'email' => 'reenabled@example.com',
            'password' => Hash::make('correct-password'),
        ]);
        $lifecycle = app(AccountLifecycleService::class);
        $lifecycle->disable($target, $actor, AccountDisableReason::TestFixture);

        $result = $lifecycle->enable($target, $actor);

        $this->assertTrue($result['changed']);
        $this->assertFalse($target->fresh()->isDisabled());
        $this->assertDatabaseHas('identity_audits', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'event' => 'account.enabled',
        ]);
        $this->post(route('login'), [
            'email' => 'reenabled@example.com',
            'password' => 'correct-password',
        ])->assertRedirect();
        $this->assertAuthenticatedAs($target);
    }

    public function test_non_superadmin_and_self_disable_transitions_are_rejected_without_changes(): void
    {
        $systemAdmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $target = User::factory()->create();
        $lifecycle = app(AccountLifecycleService::class);

        foreach ([[$target, $learner], [$systemAdmin, $systemAdmin]] as [$candidate, $actor]) {
            try {
                $lifecycle->disable($candidate, $actor, AccountDisableReason::OwnerRequest);
                $this->fail('The unauthorized account lifecycle transition should fail closed.');
            } catch (\RuntimeException) {
                $this->assertFalse($candidate->fresh()->isDisabled());
            }
        }

        $this->assertDatabaseCount('identity_audits', 0);
    }

    public function test_sqlite_migration_and_rollback_preserve_existing_user_constraints(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('This regression characterizes SQLite table-rebuild behavior.');
        }

        $migration = require database_path('migrations/2026_07_20_000012_add_disabled_account_state.php');
        $this->assertUserEnumChecksPresent();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'disabled_at'));
        $this->assertUserEnumChecksPresent();

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'disabled_at'));
        $this->assertUserEnumChecksPresent();
    }

    private function sessionFor(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('test'),
            'last_activity' => now()->timestamp,
        ]);
    }

    private function assertUserEnumChecksPresent(): void
    {
        $row = DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'",
        );
        $schema = (string) ($row?->sql ?? '');

        $this->assertStringContainsString("check (\"role\" in ('user', 'supervisor', 'admin', 'superadmin'))", $schema);
        $this->assertStringContainsString("check (\"legacy_institution_state\" in ('mapped', 'unresolved'))", $schema);
    }
}
