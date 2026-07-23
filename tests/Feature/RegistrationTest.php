<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\LegacyInstitutionState;
use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\InstitutionInvitation;
use App\Models\InstitutionMembership;
use App\Models\User;
use App\Notifications\InstitutionInvitationNotification;
use App\Services\InstitutionContext;
use App\Services\InstitutionInvitationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_only_a_personal_unverified_learner_and_exposes_no_institution_roster(): void
    {
        Notification::fake();
        $response = $this->get('/register');

        $response->assertOk()
            ->assertSeeText('Create a personal learning account')
            ->assertSeeText('Earlier personal progress is not copied or shown to institution staff.')
            ->assertDontSee('Hotel A')
            ->assertDontSee('Hotel B')
            ->assertDontSee('Politeknik Negeri Malang')
            ->assertDontSee('State Polytechnic of Malang')
            ->assertDontSee('institution_id');

        $this->post(route('register.store'), [
            'first_name' => 'Personal',
            'last_name' => 'Learner',
            'email' => 'Personal.Learner@example.com',
            'password' => 'A-reliable-test-password-2026!',
            'password_confirmation' => 'A-reliable-test-password-2026!',
            'scope_acknowledgement' => '1',
            'policy_acknowledgement' => '1',
            'role' => UserRole::Superadmin->value,
            'institution_id' => Institution::query()->where('key', 'hospitrainity-hq')->value('id'),
        ])->assertRedirect(route('verification.notice'));

        $learner = User::query()->sole();
        $this->assertSame('personal.learner@example.com', $learner->email);
        $this->assertSame(UserRole::Learner, $learner->role);
        $this->assertFalse($learner->hasVerifiedEmail());
        $this->assertSame('', $learner->instansi);
        $this->assertAuthenticatedAs($learner);
        $this->assertDatabaseCount('institution_memberships', 0);
        $this->assertDatabaseHas('identity_audits', [
            'target_user_id' => $learner->id,
            'event' => 'registration.learning_scope_acknowledged',
        ]);
    }

    public function test_public_registration_rejects_a_canonical_email_duplicate_before_insert(): void
    {
        User::factory()->create(['email' => 'existing.learner@example.com']);

        $this->post(route('register.store'), [
            'first_name' => 'Duplicate',
            'last_name' => 'Learner',
            'email' => ' Existing.Learner@Example.com ',
            'password' => 'A-reliable-test-password-2026!',
            'password_confirmation' => 'A-reliable-test-password-2026!',
            'scope_acknowledgement' => '1',
            'policy_acknowledgement' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('identity_audits', 0);
    }

    public function test_scoped_invitation_creates_only_a_verified_learner_and_is_single_use(): void
    {
        Notification::fake();
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $instructor = $this->staffMember(UserRole::Supervisor, $institution);

        $this->actingAs($instructor)
            ->post(route('supervisor.invitations.store'), ['email' => 'Invited.Learner@Example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $notification = null;
        Notification::assertSentOnDemand(
            InstitutionInvitationNotification::class,
            function (InstitutionInvitationNotification $sent) use (&$notification): bool {
                $notification = $sent;

                return true;
            },
        );
        $this->assertInstanceOf(InstitutionInvitationNotification::class, $notification);
        $token = basename(parse_url($notification->acceptUrl, PHP_URL_PATH));
        $invitation = InstitutionInvitation::query()->firstOrFail();

        $this->assertSame(43, strlen($token));
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertStringNotContainsString('invited.learner@example.com', $invitation->getRawOriginal('target_email_ciphertext'));
        $this->assertSame('invited.learner@example.com', $invitation->target_email_ciphertext);

        auth()->logout();
        $payload = [
            'name' => 'Invited Learner',
            'password' => 'A-reliable-test-password-2026!',
            'password_confirmation' => 'A-reliable-test-password-2026!',
            'terms' => '1',
            'role' => UserRole::Superadmin->value,
            'institution_id' => Institution::query()->where('key', 'hospitrainity-hq')->value('id'),
        ];

        $this->post(route('invitations.redeem', ['token' => $token]), $payload)
            ->assertRedirect(route('dashboard'));

        $learner = User::query()->where('email', 'invited.learner@example.com')->firstOrFail();
        $this->assertSame(UserRole::Learner, $learner->role);
        $this->assertTrue($learner->hasVerifiedEmail());
        $this->assertSame(LegacyInstitutionState::Mapped, $learner->legacy_institution_state);
        $this->assertSame('Politeknik Negeri Malang', $learner->instansi);
        $this->assertSame($institution->id, session(InstitutionContext::SESSION_KEY));
        $this->assertDatabaseHas('institution_memberships', [
            'institution_id' => $institution->id,
            'user_id' => $learner->id,
            'status' => InstitutionMembershipStatus::Active->value,
            'is_default' => true,
        ]);
        $this->assertDatabaseMissing('institution_memberships', [
            'institution_id' => $payload['institution_id'],
            'user_id' => $learner->id,
        ]);
        $this->assertSame(1, $invitation->fresh()->use_count);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseHas('identity_audits', ['event' => 'invitation.redeemed', 'target_user_id' => $learner->id]);

        auth()->logout();
        $this->post(route('invitations.redeem', ['token' => $token]), $payload)
            ->assertRedirect(route('invitations.unavailable'));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('institution_memberships', 2);
    }

    public function test_invitation_validation_errors_are_programmatically_associated_with_fields(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $issuer = $this->staffMember(UserRole::Admin, $institution);
        $issued = app(InstitutionInvitationService::class)
            ->issue($issuer, $institution, 'validation@example.com');
        auth()->logout();
        $url = route('invitations.redeem', ['token' => $issued['token']]);

        $this->from($url)
            ->followingRedirects()
            ->post($url, [])
            ->assertOk()
            ->assertSee('aria-describedby="invitation-name-error"', false)
            ->assertSee('aria-describedby="invitation-password-error"', false)
            ->assertSee('aria-describedby="invitation-terms-error"', false);
    }

    public function test_existing_account_must_be_authenticated_as_the_invited_email_for_multi_institution_enrollment(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $polinema = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $issuer = $this->staffMember(UserRole::Supervisor, $polinema);
        $existing = $this->staffMember(UserRole::Learner, $hq, 'existing@example.com');
        $wrong = $this->staffMember(UserRole::Learner, $hq, 'wrong@example.com');
        $issued = app(InstitutionInvitationService::class)
            ->issue($issuer, $polinema, $existing->email);

        $this->actingAs($wrong)
            ->post(route('invitations.redeem', ['token' => $issued['token']]))
            ->assertRedirect(route('invitations.unavailable'));
        $this->assertDatabaseMissing('institution_memberships', [
            'institution_id' => $polinema->id,
            'user_id' => $existing->id,
        ]);

        DB::table('sessions')->insert([
            'id' => 'other-existing-user-session',
            'user_id' => $existing->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test fixture',
            'payload' => 'test fixture',
            'last_activity' => time(),
        ]);

        $this->actingAs($existing)
            ->post(route('invitations.redeem', ['token' => $issued['token']]))
            ->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('institution_memberships', [
            'institution_id' => $polinema->id,
            'user_id' => $existing->id,
            'is_default' => false,
        ]);
        $this->assertSame($polinema->id, session(InstitutionContext::SESSION_KEY));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-existing-user-session']);
        $this->assertDatabaseHas('identity_audits', [
            'target_user_id' => $existing->id,
            'event' => 'membership.sessions_revoked',
        ]);

        $this->post(route('institution.select'), ['institution_id' => $hq->id])->assertRedirect();
        $this->assertSame($hq->id, session(InstitutionContext::SESSION_KEY));
    }

    public function test_learner_invitation_adds_only_a_learner_context_to_an_existing_staff_account(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $polinema = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $issuer = $this->staffMember(UserRole::Admin, $polinema);

        foreach ([UserRole::Supervisor, UserRole::Admin, UserRole::Superadmin] as $role) {
            $staff = $this->staffMember($role, $hq, $role->value.'@example.com');
            $issued = app(InstitutionInvitationService::class)
                ->issue($issuer, $polinema, $staff->email);

            $this->actingAs($staff)
                ->post(route('invitations.redeem', ['token' => $issued['token']]))
                ->assertRedirect(route('dashboard'));

            $membership = InstitutionMembership::query()
                ->where('institution_id', $polinema->id)
                ->where('user_id', $staff->id)
                ->firstOrFail();
            $this->assertDatabaseHas('institution_memberships', [
                'institution_id' => $polinema->id,
                'user_id' => $staff->id,
            ]);
            $this->assertDatabaseHas('institution_role_assignments', [
                'institution_membership_id' => $membership->id,
                'role' => InstitutionRole::Learner->value,
            ]);
            $this->assertDatabaseMissing('institution_role_assignments', [
                'institution_membership_id' => $membership->id,
                'role' => InstitutionRole::Instructor->value,
            ]);
            $this->assertDatabaseMissing('institution_role_assignments', [
                'institution_membership_id' => $membership->id,
                'role' => InstitutionRole::InstitutionAdmin->value,
            ]);
            $this->assertNotNull($issued['invitation']->fresh()->accepted_at);
            $this->assertSame(1, $issued['invitation']->fresh()->use_count);
            $this->get(route('supervisor.dashboard'))->assertForbidden();

            auth()->logout();
        }
    }

    public function test_session_revocation_failure_rolls_back_the_entire_redemption(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $issuer = $this->staffMember(UserRole::Supervisor, $institution);
        $service = app(InstitutionInvitationService::class);
        $issued = $service->issue($issuer, $institution, 'atomic-redemption@example.com');
        config()->set('session.table', 'missing_session_table');

        try {
            $service->redeem($issued['token'], null, [
                'name' => 'Atomic Redemption',
                'password' => 'A-reliable-test-password-2026!',
            ]);
            $this->fail('Redemption unexpectedly committed without revocable session storage.');
        } catch (QueryException) {
            // The missing table is a deliberate test double for persistence failure.
        }

        $this->assertDatabaseMissing('users', ['email' => 'atomic-redemption@example.com']);
        $this->assertDatabaseMissing('institution_memberships', ['institution_id' => $institution->id, 'provenance' => 'individual_invitation']);
        $this->assertNull($issued['invitation']->fresh()->accepted_at);
        $this->assertSame(0, $issued['invitation']->fresh()->use_count);
        $this->assertDatabaseMissing('identity_audits', ['event' => 'invitation.redeemed']);
    }

    public function test_guest_redemption_email_race_returns_the_bounded_unavailable_outcome(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $issuer = $this->staffMember(UserRole::Supervisor, $institution);
        $issued = app(InstitutionInvitationService::class)
            ->issue($issuer, $institution, 'redemption-race@example.com');
        auth()->logout();

        // SQLite trigger is a deterministic test double for another transaction
        // winning the unique-email race between the existence check and insert.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER simulate_redemption_email_race
            BEFORE INSERT ON users
            WHEN NEW.email = 'redemption-race@example.com'
                AND NEW.name <> 'Concurrent winner test double'
            BEGIN
                INSERT INTO users (name, instansi, email, password)
                VALUES (
                    'Concurrent winner test double',
                    'Politeknik Negeri Malang',
                    'redemption-race@example.com',
                    lower(hex(randomblob(32)))
                );
            END;
            SQL);

        $this->post(route('invitations.redeem', ['token' => $issued['token']]), [
            'name' => 'Race-losing learner',
            'password' => 'A-reliable-test-password-2026!',
            'password_confirmation' => 'A-reliable-test-password-2026!',
            'terms' => '1',
        ])->assertRedirect(route('invitations.unavailable'));

        $this->assertDatabaseMissing('users', ['email' => 'redemption-race@example.com']);
        $this->assertNull($issued['invitation']->fresh()->accepted_at);
        $this->assertSame(0, $issued['invitation']->fresh()->use_count);
    }

    private function staffMember(UserRole $role, Institution $institution, ?string $email = null): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'email' => $email ?? fake()->unique()->safeEmail(),
            'instansi' => $institution->name_id,
            'legacy_institution_state' => LegacyInstitutionState::Mapped,
        ]);
        InstitutionMembership::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'test_fixture',
            'joined_at' => now(),
        ]);

        $normalizedRole = match ($role) {
            UserRole::Supervisor => InstitutionRole::Instructor,
            UserRole::Admin => InstitutionRole::InstitutionAdmin,
            UserRole::Learner => InstitutionRole::Learner,
            UserRole::Superadmin => null,
        };
        if ($normalizedRole !== null) {
            $this->grantInstitutionRole($user, $normalizedRole, $institution);
        }

        return $user;
    }
}
