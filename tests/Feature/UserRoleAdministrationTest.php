<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AdministrationAudit;
use App\Models\User;
use App\Notifications\UserRoleChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserRoleAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_directory_requires_a_verified_superadmin_and_recent_password(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $this->get(route('superadmin.users.index'))->assertRedirect(route('login'));
        $this->actingAs($learner)->get(route('superadmin.users.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('superadmin.users.index'))->assertForbidden();
        $this->actingAs($superadmin)
            ->get(route('superadmin.users.index'))
            ->assertRedirect(route('password.confirm'));

        $unverified = User::factory()->unverified()->create(['role' => UserRole::Superadmin]);
        $this->actingAs($unverified)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('superadmin.users.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_superadmin_can_confirm_password_and_wrong_password_is_rejected(): void
    {
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'password' => 'correct-password',
        ]);

        $this->actingAs($superadmin)
            ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->actingAs($superadmin)
            ->post(route('password.confirm.store'), ['password' => 'correct-password'])
            ->assertRedirect(route('superadmin.users.index'));

        $this->actingAs($superadmin)
            ->get(route('superadmin.users.index'))
            ->assertOk();
    }

    public function test_password_confirmation_is_throttled(): void
    {
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'password' => 'correct-password',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($superadmin)
                ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($superadmin)
            ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
            ->assertTooManyRequests();
    }

    public function test_directory_filters_only_whitelisted_fields_and_paginates(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        User::factory()->count(21)->create(['role' => UserRole::Learner, 'instansi' => 'Other Hotel']);
        $matching = User::factory()->create([
            'name' => 'Directory Match',
            'email' => 'directory-match@example.test',
            'instansi' => 'Target Hotel',
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($superadmin)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('superadmin.users.index'))
            ->assertOk()
            ->assertViewHas('users', fn ($users): bool => $users->perPage() === 20 && $users->total() === 23);

        $filteredResponse = $this->actingAs($superadmin)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('superadmin.users.index', [
                'q' => 'Directory Match',
                'role' => UserRole::Admin->value,
                'institution' => 'Target Hotel',
                'verification' => 'verified',
            ]))
            ->assertOk()
            ->assertSee($matching->email)
            ->assertViewHas('users', fn ($users): bool => $users->total() === 1 && $users->first()->is($matching));

        $filteredResponse
            ->assertDontSee($matching->getRawOriginal('password'))
            ->assertDontSee($matching->getRememberToken());

        $this->actingAs($superadmin)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('superadmin.users.index', ['role' => 'owner']))
            ->assertSessionHasErrors('role');
    }

    public function test_general_role_change_is_audited_revokes_sessions_rotates_remember_token_and_notifies_target(): void
    {
        Notification::fake();
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create([
            'role' => UserRole::Learner,
            'remember_token' => 'original-remember-token',
        ]);
        $this->insertSessionFor($target, 'target-session');

        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.role.update', $target), [
                'role' => UserRole::Admin->value,
                'expected_role' => UserRole::Learner->value,
                'reason' => 'Assigned to review retained curriculum evidence.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertSame(UserRole::Admin, $target->role);
        $this->assertNotSame('original-remember-token', $target->getRememberToken());
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertDatabaseHas('administration_audits', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'event' => 'user.role_changed',
            'old_role' => UserRole::Learner->value,
            'new_role' => UserRole::Admin->value,
            'reason' => 'Assigned to review retained curriculum evidence.',
        ]);

        $audit = AdministrationAudit::query()->sole();
        $this->assertSame(1, $audit->metadata['sessions_revoked']);
        $this->assertNotNull($audit->created_at);
        Notification::assertSentTo(
            $target,
            UserRoleChanged::class,
            fn (UserRoleChanged $notification): bool => $notification->oldRole === UserRole::Learner
                && $notification->newRole === UserRole::Admin,
        );
    }

    public function test_superadmin_promotion_requires_separate_typed_confirmations(): void
    {
        Notification::fake();
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.promote-superadmin', $target), [
                'expected_role' => UserRole::Admin->value,
                'confirmation_email' => 'wrong@example.test',
                'confirmation_role' => UserRole::Superadmin->value,
                'reason' => 'Approved operational ownership transfer.',
            ])
            ->assertSessionHasErrors('confirmation_email');

        $this->assertSame(UserRole::Admin, $target->fresh()->role);
        $this->assertDatabaseCount('administration_audits', 0);

        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.promote-superadmin', $target), [
                'expected_role' => UserRole::Admin->value,
                'confirmation_email' => strtoupper($target->email),
                'confirmation_role' => UserRole::Superadmin->value,
                'reason' => 'Approved operational ownership transfer.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(UserRole::Superadmin, $target->fresh()->role);
        $this->assertDatabaseHas('administration_audits', [
            'event' => 'user.promoted_superadmin',
            'target_user_id' => $target->id,
            'new_role' => UserRole::Superadmin->value,
        ]);
    }

    public function test_general_endpoint_cannot_assign_superadmin_and_unverified_targets_cannot_be_elevated(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->unverified()->create(['role' => UserRole::Learner]);

        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.role.update', $target), [
                'role' => UserRole::Superadmin->value,
                'expected_role' => UserRole::Learner->value,
                'reason' => 'Attempt through the ordinary role endpoint.',
            ])
            ->assertSessionHasErrors('role');

        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.role.update', $target), [
                'role' => UserRole::Supervisor->value,
                'expected_role' => UserRole::Learner->value,
                'reason' => 'Supervisor assignment before verification.',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Learner, $target->fresh()->role);
        $this->assertDatabaseCount('administration_audits', 0);
    }

    public function test_self_change_stale_pages_and_no_op_changes_are_blocked(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create(['role' => UserRole::Supervisor]);

        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.role.update', $actor), [
                'role' => UserRole::Admin->value,
                'expected_role' => UserRole::Superadmin->value,
                'reason' => 'Self demotion must never be permitted.',
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.role.update', $target), [
                'role' => UserRole::Admin->value,
                'expected_role' => UserRole::Learner->value,
                'reason' => 'Stale role value from an earlier page render.',
            ])
            ->assertSessionHasErrors('role');

        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.role.update', $target), [
                'role' => UserRole::Supervisor->value,
                'expected_role' => UserRole::Supervisor->value,
                'reason' => 'No operation role change must be rejected.',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Superadmin, $actor->fresh()->role);
        $this->assertSame(UserRole::Supervisor, $target->fresh()->role);
        $this->assertDatabaseCount('administration_audits', 0);
    }

    public function test_role_and_session_changes_roll_back_if_audit_persistence_fails(): void
    {
        Notification::fake();
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create([
            'role' => UserRole::Learner,
            'remember_token' => 'remember-before-failed-transaction',
        ]);
        $this->insertSessionFor($target, 'rollback-session');

        DB::statement(<<<'SQL'
            CREATE TRIGGER reject_administration_audit
            BEFORE INSERT ON administration_audits
            BEGIN
                SELECT RAISE(ABORT, 'forced ADM-1 audit failure');
            END
            SQL);

        try {
            $this->actingAs($actor)
                ->withSession($this->passwordConfirmedSession())
                ->patch(route('superadmin.users.role.update', $target), [
                    'role' => UserRole::Admin->value,
                    'expected_role' => UserRole::Learner->value,
                    'reason' => 'This transaction is forced to roll back.',
                ])
                ->assertServerError();
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS reject_administration_audit');
        }

        $target->refresh();
        $this->assertSame(UserRole::Learner, $target->role);
        $this->assertSame('remember-before-failed-transaction', $target->getRememberToken());
        $this->assertDatabaseHas('sessions', ['id' => 'rollback-session', 'user_id' => $target->id]);
        $this->assertDatabaseCount('administration_audits', 0);
        Notification::assertNothingSent();
    }

    public function test_demoting_another_superadmin_preserves_at_least_one_superadmin(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.role.update', $target), [
                'role' => UserRole::Admin->value,
                'expected_role' => UserRole::Superadmin->value,
                'reason' => 'Remove account-administration responsibility.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, User::query()->where('role', UserRole::Superadmin->value)->count());
        $this->assertTrue($actor->fresh()->isSuperAdmin());
        $this->assertTrue($target->fresh()->isAdmin());
    }

    public function test_role_changes_require_recent_password_and_are_throttled(): void
    {
        $unconfirmedActor = User::factory()->create(['role' => UserRole::Superadmin]);
        $targets = User::factory()->count(6)->create(['role' => UserRole::Learner]);

        $this->actingAs($unconfirmedActor)
            ->patch(route('superadmin.users.role.update', $targets->first()), [
                'role' => UserRole::Admin->value,
                'expected_role' => UserRole::Learner->value,
                'reason' => 'Missing recent password confirmation.',
            ])
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($unconfirmedActor)
            ->withSession(['auth.password_confirmed_at' => time() - 901])
            ->patch(route('superadmin.users.role.update', $targets->first()), [
                'role' => UserRole::Admin->value,
                'expected_role' => UserRole::Learner->value,
                'reason' => 'Expired password confirmation is not recent.',
            ])
            ->assertRedirect(route('password.confirm'));

        $actor = User::factory()->create(['role' => UserRole::Superadmin]);

        foreach ($targets->take(5) as $target) {
            $this->actingAs($actor)
                ->withSession($this->passwordConfirmedSession())
                ->patch(route('superadmin.users.role.update', $target), [
                    'role' => UserRole::Admin->value,
                    'expected_role' => UserRole::Learner->value,
                    'reason' => 'Approved content administration assignment.',
                ])
                ->assertSessionHas('success');
        }

        $lastTarget = $targets->last();
        $this->actingAs($actor)
            ->withSession($this->passwordConfirmedSession())
            ->patch(route('superadmin.users.role.update', $lastTarget), [
                'role' => UserRole::Admin->value,
                'expected_role' => UserRole::Learner->value,
                'reason' => 'This request must be rate limited.',
            ])
            ->assertTooManyRequests();

        $this->assertSame(UserRole::Learner, $lastTarget->fresh()->role);
    }

    /** @return array{auth.password_confirmed_at: int} */
    private function passwordConfirmedSession(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    private function insertSessionFor(User $user, string $sessionId): void
    {
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'ADM-1 test session',
            'payload' => '',
            'last_activity' => time(),
        ]);
    }
}
