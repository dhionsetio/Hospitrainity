<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use App\Services\InstitutionContext;
use App\Services\WorkContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstitutionRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_admin_can_grant_and_revoke_instructor_only_in_the_exact_tenant(): void
    {
        config(['session.driver' => 'database']);
        $institution = $this->institution('hospitrainity-hq');
        $actor = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($actor, $institution, InstitutionRole::InstitutionAdmin);
        $target = User::factory()->create(['remember_token' => 'before-change']);
        $targetMembership = $this->membership($target, $institution, InstitutionRole::Learner);
        DB::table('sessions')->insert([
            'id' => 'target-session',
            'user_id' => $target->id,
            'payload' => 'test-double-session-payload',
            'last_activity' => time(),
        ]);

        $this->actingAs($actor)
            ->withSession($this->institutionAdminSession($institution))
            ->patch(route('supervisor.institution-roles.update', $targetMembership), [
                'role' => InstitutionRole::Instructor->value,
                'action' => 'grant',
            ])->assertRedirect()
            ->assertSessionHas('status', 'The institution role has been granted.');

        $this->assertDatabaseHas('institution_role_assignments', [
            'institution_membership_id' => $targetMembership->id,
            'role' => InstitutionRole::Instructor->value,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertNotSame('before-change', $target->fresh()->remember_token);
        $this->assertDatabaseHas('identity_audits', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'institution_id' => $institution->id,
            'event' => 'institution_role.granted',
        ]);

        $this->actingAs($actor)
            ->withSession($this->institutionAdminSession($institution))
            ->patch(route('supervisor.institution-roles.update', $targetMembership), [
                'role' => InstitutionRole::Instructor->value,
                'action' => 'revoke',
            ])->assertRedirect()
            ->assertSessionHas('status', 'The institution role has been revoked.');

        $this->assertNotNull(InstitutionRoleAssignment::query()
            ->where('institution_membership_id', $targetMembership->id)
            ->where('role', InstitutionRole::Instructor->value)
            ->sole()->revoked_at);
    }

    public function test_institution_admin_cannot_cross_tenants_self_manage_or_manage_institution_admin(): void
    {
        $hq = $this->institution('hospitrainity-hq');
        $polinema = $this->institution('politeknik-negeri-malang');
        $actor = User::factory()->create(['role' => UserRole::Learner]);
        $actorMembership = $this->membership($actor, $hq, InstitutionRole::InstitutionAdmin);
        $target = User::factory()->create();
        $hqTargetMembership = $this->membership($target, $hq, InstitutionRole::Learner);
        $otherTarget = User::factory()->create();
        $otherMembership = $this->membership($otherTarget, $polinema, InstitutionRole::Learner);
        $session = $this->institutionAdminSession($hq);

        $this->actingAs($actor)->withSession($session)
            ->patch(route('supervisor.institution-roles.update', $otherMembership), [
                'role' => InstitutionRole::Instructor->value,
                'action' => 'grant',
            ])->assertNotFound();

        $this->actingAs($actor)->withSession($session)
            ->from(route('supervisor.institution-roles.index'))
            ->patch(route('supervisor.institution-roles.update', $actorMembership), [
                'role' => InstitutionRole::Instructor->value,
                'action' => 'grant',
            ])->assertRedirect(route('supervisor.institution-roles.index'))
            ->assertSessionHasErrors('role');

        $this->actingAs($actor)->withSession($session)
            ->patch(route('supervisor.institution-roles.update', $hqTargetMembership), [
                'role' => InstitutionRole::InstitutionAdmin->value,
                'action' => 'grant',
            ])->assertForbidden();
    }

    public function test_only_system_admin_can_grant_institution_admin_and_instructor_cannot_open_staff_management(): void
    {
        $institution = $this->institution('hospitrainity-hq');
        $target = User::factory()->create();
        $targetMembership = $this->membership($target, $institution, InstitutionRole::Learner);
        $systemAdmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->actingAs($systemAdmin)
            ->withSession([
                InstitutionContext::SESSION_KEY => $institution->id,
                'auth.password_confirmed_at' => time(),
            ])
            ->patch(route('superadmin.institution-roles.update', $targetMembership), [
                'role' => InstitutionRole::InstitutionAdmin->value,
                'action' => 'grant',
            ])->assertRedirect();

        $this->assertDatabaseHas('institution_role_assignments', [
            'institution_membership_id' => $targetMembership->id,
            'role' => InstitutionRole::InstitutionAdmin->value,
            'revoked_at' => null,
        ]);

        $instructor = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($instructor, $institution, InstitutionRole::Instructor);
        $this->actingAs($instructor)
            ->withSession([
                WorkContext::SESSION_ROLE_KEY => 'instructor',
                InstitutionContext::SESSION_KEY => $institution->id,
                'auth.password_confirmed_at' => time(),
            ])
            ->get(route('supervisor.institution-roles.index'))
            ->assertForbidden();
    }

    public function test_staff_role_page_requires_recent_password_and_returns_to_safe_get_page(): void
    {
        $institution = $this->institution('hospitrainity-hq');
        $actor = User::factory()->create([
            'role' => UserRole::Learner,
            'password' => 'correct-password',
        ]);
        $this->membership($actor, $institution, InstitutionRole::InstitutionAdmin);

        $this->actingAs($actor)
            ->withSession([
                WorkContext::SESSION_ROLE_KEY => 'institution_admin',
                InstitutionContext::SESSION_KEY => $institution->id,
            ])
            ->get(route('supervisor.institution-roles.index'))
            ->assertRedirect(route('password.confirm'));

        $this->get(route('password.confirm'))
            ->assertOk()
            ->assertSeeText('Institution staff roles are security-sensitive.');

        $this->post(route('password.confirm.store'), ['password' => 'correct-password'])
            ->assertRedirect(route('supervisor.institution-roles.index', absolute: false));
        $this->get(route('supervisor.institution-roles.index'))->assertOk();
    }

    private function institution(string $key): Institution
    {
        return Institution::query()->where('key', $key)->firstOrFail();
    }

    private function membership(User $user, Institution $institution, InstitutionRole $role): InstitutionMembership
    {
        $membership = InstitutionMembership::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'test_fixture',
            'joined_at' => now(),
        ]);
        InstitutionRoleAssignment::query()->create([
            'institution_membership_id' => $membership->id,
            'role' => $role,
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
        ]);

        return $membership;
    }

    /** @return array<string, int|string> */
    private function institutionAdminSession(Institution $institution): array
    {
        return [
            WorkContext::SESSION_ROLE_KEY => 'institution_admin',
            InstitutionContext::SESSION_KEY => $institution->id,
            'auth.password_confirmed_at' => time(),
        ];
    }
}
