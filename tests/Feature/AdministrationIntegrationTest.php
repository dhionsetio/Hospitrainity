<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AdministrationAudit;
use App\Models\CurriculumDraftEvent;
use App\Models\User;
use App\Services\Curriculum\CurriculumDraftWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use LogicException;
use Tests\TestCase;

class AdministrationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_review_requires_a_recently_confirmed_superadmin(): void
    {
        $unverified = User::factory()->unverified()->create(['role' => UserRole::Superadmin]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->get(route('superadmin.audit.index'))->assertRedirect(route('login'));
        $this->actingAs($unverified)->get(route('superadmin.audit.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs($learner)->get(route('superadmin.audit.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('superadmin.audit.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('superadmin.audit.index'))->assertForbidden();
        $this->actingAs($superadmin)->get(route('superadmin.audit.index'))->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->actingAs($superadmin)
            ->get(route('superadmin.audit.index'))
            ->assertOk();
    }

    public function test_password_confirmation_returns_to_the_allowlisted_audit_destination(): void
    {
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'password' => 'correct-password',
        ]);
        $auditUrl = route('superadmin.audit.index', [
            'category' => 'curriculum',
            'actor' => 'Reviewer',
            'event' => str_repeat('x', 81),
            'unexpected' => 'discarded',
        ]);

        $this->actingAs($superadmin)
            ->get($auditUrl)
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($superadmin)
            ->get(route('password.confirm'))
            ->assertSee(__('admin.confirm_password_description.audit'));

        $this->actingAs($superadmin)
            ->post(route('password.confirm.store'), ['password' => 'correct-password'])
            ->assertRedirect(route('superadmin.audit.index', [
                'category' => 'curriculum',
                'actor' => 'Reviewer',
            ], absolute: false))
            ->assertSessionMissing('url.intended')
            ->assertSessionHas('status', __('admin.password_confirmed.audit'));
    }

    public function test_password_confirmation_rejects_untrusted_intended_destinations(): void
    {
        $superadmin = User::factory()->create([
            'role' => UserRole::Superadmin,
            'password' => 'correct-password',
        ]);

        foreach (['https://attacker.example/collect', 'http://operator@localhost/superadmin/audit', route('dashboard')] as $untrusted) {
            $this->withSession(['url.intended' => $untrusted])
                ->actingAs($superadmin)
                ->post(route('password.confirm.store'), ['password' => 'correct-password'])
                ->assertRedirect(route('superadmin.users.index', absolute: false))
                ->assertSessionMissing('url.intended');
        }
    }

    public function test_audit_review_merges_filters_and_sanitizes_identity_and_curriculum_events(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create(['role' => UserRole::Learner]);
        $draft = app(CurriculumDraftWorkspace::class)->create($superadmin, [
            'source' => 'empty',
            'title' => 'Audit test draft',
            'content_version' => '9.0.0',
        ]);
        $identity = AdministrationAudit::query()->create([
            'actor_user_id' => $superadmin->id,
            'target_user_id' => $target->id,
            'event' => 'user.role_changed',
            'old_role' => UserRole::Learner,
            'new_role' => UserRole::Admin,
            'reason' => "  Approved support coverage.\0  ",
            'ip_address' => '127.0.0.1',
            'user_agent' => "ADM-6\r\nBrowser",
            'metadata' => [
                'sessions_revoked' => 2,
                'password' => 'must-never-persist',
                'nested' => ['session_id' => 'hidden-session', 'safe_marker' => 'retained-marker'],
            ],
            'created_at' => now()->addSecond(),
        ]);
        CurriculumDraftEvent::query()->create([
            'curriculum_draft_id' => $draft->id,
            'actor_id' => $superadmin->id,
            'event_type' => 'security_test_event',
            'revision' => $draft->revision,
            'metadata' => ['response_payload' => 'hidden-response', 'safe_marker' => 'curriculum-marker'],
            'created_at' => now()->addSeconds(2),
        ]);

        $this->assertSame('Approved support coverage.', $identity->fresh()->reason);
        $this->assertSame('ADM-6 Browser', $identity->fresh()->user_agent);
        $this->assertArrayNotHasKey('password', $identity->fresh()->metadata);
        $this->assertArrayNotHasKey('session_id', $identity->fresh()->metadata['nested']);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->actingAs($superadmin)
            ->get(route('superadmin.audit.index'));
        $response->assertOk()
            ->assertSee('user.role_changed')
            ->assertSee('draft_created_empty')
            ->assertSee('security_test_event')
            ->assertSee('retained-marker')
            ->assertSee('curriculum-marker')
            ->assertDontSee('must-never-persist')
            ->assertDontSee('hidden-session')
            ->assertDontSee('hidden-response');

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->actingAs($superadmin)
            ->get(route('superadmin.audit.index', ['category' => 'identity']))
            ->assertOk()
            ->assertViewHas('entries', static fn ($entries): bool => collect($entries->items())->pluck('event')->all() === ['user.role_changed']);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->actingAs($superadmin)
            ->get(route('superadmin.audit.index', ['event' => 'security_test_event']))
            ->assertOk()
            ->assertViewHas('entries', static fn ($entries): bool => collect($entries->items())->pluck('event')->all() === ['security_test_event']);
    }

    public function test_canonical_first_navigation_and_destination_routes_match_role_policy(): void
    {
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        app(CurriculumDraftWorkspace::class)->create($admin, [
            'source' => 'empty',
            'title' => 'Navigation draft',
            'content_version' => '9.1.0',
        ]);

        $adminDashboard = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminDashboard->assertOk()
            ->assertSee('href="'.route('admin.curriculum-drafts.index').'"', false)
            ->assertSee('href="'.route('admin.curriculum-exercises.index').'"', false)
            ->assertSee('href="'.route('admin.progress.index').'"', false)
            ->assertSee('href="'.route('admin.legacy-evidence.index').'"', false)
            ->assertDontSee('href="'.route('superadmin.users.index').'"', false)
            ->assertDontSee('href="'.route('superadmin.audit.index').'"', false);

        $this->actingAs($admin)->get(route('admin.curriculum-exercises.index'))
            ->assertOk()
            ->assertSee('Navigation draft')
            ->assertSee('Manage exercises');
        $this->actingAs($admin)->get(route('admin.legacy-evidence.index'))
            ->assertOk()
            ->assertSee('Read-only')
            ->assertSee(route('admin.modules.index'), false);

        $superadminDashboard = $this->actingAs($superadmin)->get(route('superadmin.dashboard'));
        $superadminDashboard->assertOk()
            ->assertSee('href="'.route('superadmin.users.index').'"', false)
            ->assertSee('href="'.route('superadmin.audit.index').'"', false);
        $this->actingAs($superadmin)->get(route('superadmin.curriculum-exercises.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('superadmin.legacy-evidence.index'))->assertOk();
        $this->actingAs($learner)->get(route('admin.curriculum-exercises.index'))->assertForbidden();
        $this->actingAs($learner)->get(route('admin.legacy-evidence.index'))->assertForbidden();
    }

    public function test_audit_review_uses_a_fixed_page_size_and_preserves_filters(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $draft = app(CurriculumDraftWorkspace::class)->create($superadmin, [
            'source' => 'empty',
            'title' => 'Paginated audit draft',
            'content_version' => '9.3.0',
        ]);
        for ($index = 1; $index <= 55; $index++) {
            CurriculumDraftEvent::query()->create([
                'curriculum_draft_id' => $draft->id,
                'actor_id' => $superadmin->id,
                'event_type' => 'pagination_event',
                'revision' => $index,
                'metadata' => ['sequence' => $index],
                'created_at' => now()->addSeconds($index),
            ]);
        }

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->actingAs($superadmin)
            ->get(route('superadmin.audit.index', ['category' => 'curriculum', 'event' => 'pagination_event']));

        $response->assertOk()
            ->assertViewHas('entries', static fn ($entries): bool => $entries->count() === 50 && $entries->total() === 55)
            ->assertSee('category=curriculum', false)
            ->assertSee('event=pagination_event', false)
            ->assertSee('page=2', false);
    }

    public function test_audit_review_access_is_security_logged_without_filter_values(): void
    {
        Log::spy();
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->actingAs($superadmin)
            ->get(route('superadmin.audit.index', ['category' => 'curriculum', 'actor' => 'private-search-value']))
            ->assertOk();

        Log::shouldHaveReceived('notice')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'administration.audit_reviewed'
                && $context['actor_user_id'] === $superadmin->id
                && $context['filter_keys'] === ['category', 'actor']
                && ! in_array('private-search-value', $context, true),
        );
    }

    public function test_audit_models_reject_ordinary_updates_and_deletes(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $target = User::factory()->create(['role' => UserRole::Learner]);
        $audit = AdministrationAudit::query()->create([
            'actor_user_id' => $superadmin->id,
            'target_user_id' => $target->id,
            'event' => 'user.role_changed',
            'old_role' => UserRole::Learner,
            'new_role' => UserRole::Admin,
            'reason' => 'Preserve this test audit record.',
            'created_at' => now(),
        ]);

        try {
            $audit->forceFill(['reason' => 'Tampered'])->save();
            $this->fail('An administration audit update unexpectedly succeeded.');
        } catch (LogicException $exception) {
            $this->assertSame('Administration audit records are append-only.', $exception->getMessage());
        }

        $draft = app(CurriculumDraftWorkspace::class)->create($superadmin, [
            'source' => 'empty',
            'title' => 'Immutable event draft',
            'content_version' => '9.4.0',
        ]);
        $event = $draft->events()->firstOrFail();
        try {
            $event->delete();
            $this->fail('A curriculum audit deletion unexpectedly succeeded.');
        } catch (LogicException $exception) {
            $this->assertSame('Curriculum audit events are append-only.', $exception->getMessage());
        }
    }
}
