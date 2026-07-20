<?php

namespace Tests\Feature;

use App\Enums\AccountDisableReason;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\LegacyInstitutionState;
use App\Enums\UserRole;
use App\Exceptions\InvitationUnavailableException;
use App\Models\Institution;
use App\Models\InstitutionInvitation;
use App\Models\InstitutionMembership;
use App\Models\User;
use App\Services\AccountLifecycleService;
use App\Services\InstitutionContext;
use App\Services\InstitutionInvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class InstitutionInvitationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_invite_in_tenant_context_while_content_author_context_has_no_invitation_routes(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $supervisor = $this->member(UserRole::Supervisor, $hq);
        $learner = $this->member(UserRole::Learner, $hq);
        $unresolved = User::factory()->create([
            'role' => UserRole::Supervisor,
            'instansi' => 'Unreviewed Legacy Hotel',
            'legacy_institution_state' => LegacyInstitutionState::Unresolved,
        ]);

        $this->actingAs($supervisor)->get(route('supervisor.invitations.index'))->assertOk();
        $this->assertFalse(Route::has('admin.invitations.index'));
        $this->actingAs($learner)->get(route('supervisor.invitations.index'))->assertForbidden();
        $this->actingAs($unresolved)->get(route('supervisor.invitations.index'))->assertForbidden();
        $this->actingAs($unresolved)->get(route('supervisor.dashboard'))->assertForbidden();
    }

    public function test_superadmin_can_select_an_active_institution_but_scoped_staff_cannot_cross_scope(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $polinema = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $supervisor = $this->member(UserRole::Supervisor, $hq);
        $learner = $this->member(UserRole::Learner, $hq);
        $unknownInstitutionId = (string) Str::uuid();

        $this->actingAs($superadmin)
            ->post(route('superadmin.invitations.institution'), ['institution_id' => $polinema->id])
            ->assertRedirect();
        $this->assertSame($polinema->id, session(InstitutionContext::SESSION_KEY));

        $this->actingAs($supervisor)
            ->post(route('supervisor.invitations.institution'), ['institution_id' => $polinema->id])
            ->assertForbidden();
        $this->post(route('supervisor.invitations.institution'), ['institution_id' => $unknownInstitutionId])
            ->assertForbidden();

        $this->actingAs($learner)
            ->post(route('institution.select'), ['institution_id' => $polinema->id])
            ->assertForbidden();
        $this->post(route('institution.select'), ['institution_id' => $unknownInstitutionId])
            ->assertForbidden();
    }

    public function test_scoped_staff_cannot_use_revoke_status_to_confirm_another_institutions_invitation_id(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $polinema = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $hqIssuer = $this->member(UserRole::Supervisor, $hq);
        $polinemaSupervisor = $this->member(UserRole::Supervisor, $polinema);
        $issued = app(InstitutionInvitationService::class)
            ->issue($hqIssuer, $hq, 'cross-scope-id@example.com');

        $this->actingAs($polinemaSupervisor)
            ->delete(route('supervisor.invitations.destroy', ['invitation' => $issued['invitation']->id]))
            ->assertNotFound();
        $this->delete(route('supervisor.invitations.destroy', ['invitation' => (string) Str::uuid()]))
            ->assertNotFound();

        $this->assertNull($issued['invitation']->fresh()->revoked_at);
    }

    public function test_reissuing_revokes_the_prior_token_and_staff_history_masks_the_target(): void
    {
        Notification::fake();
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $supervisor = $this->member(UserRole::Supervisor, $hq);
        $service = app(InstitutionInvitationService::class);
        $first = $service->issue($supervisor, $hq, 'private.target@example.com');
        $second = $service->issue($supervisor, $hq, 'PRIVATE.TARGET@example.com');

        $this->assertNotNull($first['invitation']->fresh()->revoked_at);
        $this->assertNull($second['invitation']->fresh()->revoked_at);
        $this->assertSame(1, InstitutionInvitation::query()
            ->where('institution_id', $hq->id)
            ->where('target_email_hash', $second['invitation']->target_email_hash)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->count());
        $this->assertDatabaseHas('identity_audits', [
            'actor_user_id' => $supervisor->id,
            'institution_id' => $hq->id,
            'invitation_id' => $first['invitation']->id,
            'event' => 'invitation.superseded',
        ]);
        $this->get(route('invitations.accept', ['token' => $first['token']]))->assertNotFound();

        $this->actingAs($supervisor)
            ->get(route('supervisor.invitations.index'))
            ->assertOk()
            ->assertDontSee('private.target@example.com')
            ->assertSee('p••••••••@example.com');

        $auditJson = DB::table('identity_audits')->orderBy('id')->get()->toJson();
        $this->assertStringNotContainsString($first['token'], $auditJson);
        $this->assertStringNotContainsString($second['token'], $auditJson);
        $this->assertStringNotContainsString('private.target@example.com', $auditJson);
    }

    public function test_service_revalidates_current_role_and_membership_inside_invitation_writes(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $service = app(InstitutionInvitationService::class);
        $membershipRevokedActor = $this->member(UserRole::Supervisor, $hq);
        $issued = $service->issue($membershipRevokedActor, $hq, 'retained@example.com');
        InstitutionMembership::query()
            ->where('user_id', $membershipRevokedActor->id)
            ->where('institution_id', $hq->id)
            ->update([
                'status' => InstitutionMembershipStatus::Revoked->value,
                'revoked_at' => now(),
            ]);

        $roleChangedActor = $this->member(UserRole::Admin, $hq);
        User::query()->whereKey($roleChangedActor->id)->update(['role' => UserRole::Learner->value]);

        foreach ([
            fn () => $service->issue($membershipRevokedActor, $hq, 'blocked-membership@example.com'),
            fn () => $service->revoke($membershipRevokedActor, $issued['invitation']),
            fn () => $service->issue($roleChangedActor, $hq, 'blocked-role@example.com'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Invitation write accepted stale authorization state.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertNull($issued['invitation']->fresh()->revoked_at);
        $this->assertDatabaseMissing('institution_invitations', [
            'target_email_hash' => hash_hmac('sha256', 'blocked-membership@example.com', (string) config('app.key')),
        ]);
        $this->assertDatabaseMissing('institution_invitations', [
            'target_email_hash' => hash_hmac('sha256', 'blocked-role@example.com', (string) config('app.key')),
        ]);
    }

    public function test_disabled_accounts_cannot_issue_or_redeem_invitations(): void
    {
        config(['session.driver' => 'database']);
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $systemAdmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $issuer = $this->member(UserRole::Supervisor, $hq);
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email' => 'disabled-invitation@example.com',
        ]);
        $service = app(InstitutionInvitationService::class);
        $issued = $service->issue($issuer, $hq, $learner->email);
        $lifecycle = app(AccountLifecycleService::class);
        $lifecycle->disable($issuer, $systemAdmin, AccountDisableReason::SecurityHold);
        $lifecycle->disable($learner, $systemAdmin, AccountDisableReason::SecurityHold);

        try {
            $service->issue($issuer, $hq, 'blocked-disabled-actor@example.com');
            $this->fail('A disabled staff account issued an invitation.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        try {
            $service->redeem($issued['token'], $learner, null);
            $this->fail('A disabled learner redeemed an invitation.');
        } catch (InvitationUnavailableException) {
            $this->addToAssertionCount(1);
        }

        $this->assertNull($issued['invitation']->fresh()->accepted_at);
    }

    public function test_expired_and_revoked_tokens_have_the_same_public_failure_contract(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $issuer = $this->member(UserRole::Admin, $hq);
        $service = app(InstitutionInvitationService::class);
        $expired = $service->issue($issuer, $hq, 'expired@example.com');
        $revoked = $service->issue($issuer, $hq, 'revoked@example.com');
        InstitutionInvitation::query()->whereKey($expired['invitation']->id)->update(['expires_at' => now()->subMinute()]);
        $service->revoke($issuer, $revoked['invitation']);

        foreach ([$expired['token'], $revoked['token']] as $token) {
            $this->get(route('invitations.accept', ['token' => $token]))->assertNotFound();
            $this->post(route('invitations.redeem', ['token' => $token]))
                ->assertRedirect(route('invitations.unavailable'));
        }
    }

    public function test_mail_failure_revokes_the_new_invitation_and_returns_a_bounded_error(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $issuer = $this->member(UserRole::Supervisor, $hq);
        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('test double: mail transport unavailable'));

        $this->actingAs($issuer)
            ->from(route('supervisor.invitations.index'))
            ->post(route('supervisor.invitations.store'), [
                'email' => 'delivery.failure@example.com',
            ])
            ->assertRedirect(route('supervisor.invitations.index'))
            ->assertSessionHasErrors('email');

        $invitation = InstitutionInvitation::query()->sole();
        $this->assertNotNull($invitation->revoked_at);
        $this->assertSame($issuer->id, $invitation->revoked_by_user_id);
        $this->assertDatabaseHas('identity_audits', [
            'actor_user_id' => $issuer->id,
            'institution_id' => $hq->id,
            'invitation_id' => $invitation->id,
            'event' => 'invitation.delivery_failed',
        ]);
        $this->assertStringNotContainsString(
            'delivery.failure@example.com',
            DB::table('identity_audits')->get()->toJson(),
        );
    }

    public function test_malformed_invitation_email_shape_reaches_validation_without_a_rate_limiter_error(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $issuer = $this->member(UserRole::Supervisor, $hq);

        $this->actingAs($issuer)
            ->from(route('supervisor.invitations.index'))
            ->post(route('supervisor.invitations.store'), [
                'email' => ['unexpected' => 'nested-value'],
            ])
            ->assertRedirect(route('supervisor.invitations.index'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('institution_invitations', 0);
    }

    public function test_valid_token_page_is_not_cached_and_does_not_send_a_referrer(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $issuer = $this->member(UserRole::Supervisor, $hq);
        $issued = app(InstitutionInvitationService::class)->issue($issuer, $hq, 'header-test@example.com');

        $this->get(route('invitations.accept', ['token' => $issued['token']]))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');
    }

    public function test_corrupted_encrypted_target_fails_closed_without_disclosing_or_consuming_the_invitation(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $issuer = $this->member(UserRole::Admin, $hq);
        $issued = app(InstitutionInvitationService::class)->issue($issuer, $hq, 'corrupted@example.com');
        DB::table('institution_invitations')->where('id', $issued['invitation']->id)->update([
            'target_email_ciphertext' => 'not-valid-encrypted-data',
        ]);

        $this->get(route('invitations.accept', ['token' => $issued['token']]))->assertNotFound();
        $this->post(route('invitations.redeem', ['token' => $issued['token']]))
            ->assertRedirect(route('invitations.unavailable'));
        $this->assertNull($issued['invitation']->fresh()->accepted_at);
        $this->assertSame(0, $issued['invitation']->fresh()->use_count);
    }

    public function test_identity_records_require_lifecycle_changes_instead_of_direct_deletion(): void
    {
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $issuer = $this->member(UserRole::Supervisor, $hq);
        $invitation = app(InstitutionInvitationService::class)
            ->issue($issuer, $hq, 'retained-evidence@example.com')['invitation'];

        foreach ([$invitation, $issuer->institutionMemberships()->firstOrFail(), $hq] as $record) {
            try {
                $record->delete();
                $this->fail($record::class.' unexpectedly allowed direct deletion.');
            } catch (LogicException) {
                $this->assertTrue($record->fresh()->exists);
            }
        }
    }

    private function member(UserRole $role, Institution $institution): User
    {
        $user = User::factory()->create([
            'role' => $role,
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

        return $user;
    }
}
