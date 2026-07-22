<?php

namespace Tests\Feature;

use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserOnboardingState;
use App\Services\NextActionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class NextActionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_priority_is_onboarding_then_the_first_real_incomplete_module(): void
    {
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $request = Request::create('/dashboard');
        $chapters = collect([
            ['code' => 'MODULE-ONE', 'title' => 'First module', 'progress' => 40],
            ['code' => 'MODULE-TWO', 'title' => 'Second module', 'progress' => 0],
        ]);
        $resolver = app(NextActionResolver::class);

        $onboarding = $resolver->learner($request, $learner, $chapters);
        $this->assertSame('Getting started', $onboarding['eyebrow']);
        $this->assertSame(route('onboarding.show'), $onboarding['url']);

        $this->finishedOnboarding($learner, 'learner');
        $learning = $resolver->learner($request, $learner, $chapters);
        $this->assertSame('Continue', $learning['eyebrow']);
        $this->assertSame('First module', $learning['title']);
        $this->assertSame(route('curriculum.chapters.show', 'MODULE-ONE'), $learning['url']);
    }

    public function test_staff_actions_use_only_available_role_tasks_and_truthful_empty_states(): void
    {
        $resolver = app(NextActionResolver::class);
        $request = Request::create('/dashboard');
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $this->grantInstitutionRole($supervisor, InstitutionRole::Instructor);
        $this->finishedOnboarding($supervisor, 'instructor');

        $empty = new LengthAwarePaginator([], 0, 15);
        $setup = $resolver->supervisor($request, $supervisor, $empty);
        $this->assertSame('No approved learners in this institution yet', $setup['title']);
        $this->assertSame(route('supervisor.invitations.index'), $setup['url']);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->finishedOnboarding($admin, 'content_author');
        $content = $resolver->administration($request, $admin, ['in_review' => 1, 'editable' => 1]);
        $this->assertSame('Review content awaiting a decision', $content['title']);
        $this->assertSame(route('admin.curriculum-drafts.index'), $content['url']);

        $systemAdmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $this->finishedOnboarding($systemAdmin, 'system_admin');
        $workspace = $resolver->administration($request, $systemAdmin, ['in_review' => 0, 'editable' => 0]);
        $this->assertSame('No content work in progress', $workspace['title']);
        $this->assertSame(route('superadmin.curriculum-drafts.index'), $workspace['url']);
    }

    private function finishedOnboarding(User $user, string $contextRole): void
    {
        UserOnboardingState::query()->create([
            'user_id' => $user->id,
            'context_role' => $contextRole,
            'status' => 'skipped',
            'current_step' => 0,
            'started_at' => now(),
            'skipped_at' => now(),
        ]);
    }
}
