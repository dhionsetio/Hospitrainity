<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\UserCapability;
use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use App\Models\UserCapabilityAssignment;
use App\Services\WorkContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkContextAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_role_learner_has_no_role_switcher_and_direct_page_returns_to_dashboard(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($learner, $institution, InstitutionRole::Learner);

        $this->assertFalse(app(WorkContext::class)->hasAlternativeRole($learner));
        $this->actingAs($learner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="'.__('Switch learning context').'"', false)
            ->assertDontSee('href="'.route('work-context.index').'"', false)
            ->assertDontSee('Switch role');
        $this->get(route('work-context.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_learner_with_an_instructor_assignment_can_access_role_switching(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($learner, $institution, InstitutionRole::Instructor);

        $this->assertTrue(app(WorkContext::class)->hasAlternativeRole($learner));
        $this->actingAs($learner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('work-context.index').'"', false)
            ->assertSee('Switch role');
        $this->get(route('work-context.index'))
            ->assertOk()
            ->assertSee('Learner')
            ->assertSee('Instructor')
            ->assertSee('aria-current="true"', false)
            ->assertSee('Current context')
            ->assertSee('hsp-context-choice', false)
            ->assertDontSee('Continue in this context');
    }

    public function test_one_account_switches_between_content_instructor_and_learner_without_combining_authority(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $membership = $this->membership($user, $institution, InstitutionRole::Instructor);
        UserCapabilityAssignment::query()->create([
            'user_id' => $user->id,
            'capability' => UserCapability::ContentAuthor,
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
        ]);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('supervisor.dashboard'))->assertForbidden();
        $this->get(route('dashboard'))->assertForbidden();

        $this->post(route('work-context.store'), [
            'role' => 'instructor',
            'institution_id' => $institution->id,
        ])->assertRedirect(route('supervisor.dashboard'));
        $this->get(route('supervisor.dashboard'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('dashboard'))->assertForbidden();

        $this->post(route('work-context.store'), ['role' => 'learner'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('supervisor.dashboard'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->assertSame('learner', session(WorkContext::SESSION_ROLE_KEY));
        $this->assertNotNull($membership->id);
    }

    public function test_institution_admin_assignment_never_grants_global_system_admin(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $institutionAdmin = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($institutionAdmin, $institution, InstitutionRole::InstitutionAdmin);

        $contexts = app(WorkContext::class)->available(request(), $institutionAdmin);
        $this->assertTrue($contexts->contains(fn (array $context): bool => $context['role']->value === 'institution_admin'));
        $this->assertFalse($contexts->contains(fn (array $context): bool => $context['role']->value === 'system_admin'));

        $this->actingAs($institutionAdmin)
            ->post(route('work-context.store'), [
                'role' => 'institution_admin',
                'institution_id' => $institution->id,
            ])->assertRedirect(route('supervisor.dashboard'));
        $this->get(route('superadmin.dashboard'))->assertForbidden();
    }

    public function test_system_admin_preview_is_bannered_audited_and_does_not_assign_a_tenant_role(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $systemAdmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->actingAs($systemAdmin)
            ->post(route('work-context.store'), [
                'role' => 'instructor',
                'institution_id' => $institution->id,
                'preview' => '1',
            ])->assertRedirect(route('supervisor.dashboard'));

        $this->get(route('supervisor.dashboard'))
            ->assertOk()
            ->assertSeeText('Preview mode is active. Actions still use your System Admin authority and are audited.');
        $this->assertTrue(session(WorkContext::SESSION_PREVIEW_KEY));
        $this->assertDatabaseHas('identity_audits', [
            'actor_user_id' => $systemAdmin->id,
            'institution_id' => $institution->id,
            'event' => 'work_context.preview_started',
        ]);
        $this->assertDatabaseCount('institution_role_assignments', 0);

        $this->post(route('work-context.store'), ['role' => 'system_admin'])
            ->assertRedirect(route('superadmin.dashboard'));
        $this->assertNull(session(WorkContext::SESSION_PREVIEW_KEY));
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
}
