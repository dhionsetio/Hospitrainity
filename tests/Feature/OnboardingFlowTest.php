<?php

namespace Tests\Feature;

use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserOnboardingState;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_onboarding_is_read_safe_resumable_completable_and_restartable(): void
    {
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $this->actingAs($learner)->get(route('onboarding.show'))
            ->assertOk()
            ->assertSee('Step 1 of 3')
            ->assertSee('Choose where to save progress');
        $this->assertDatabaseCount('user_onboarding_states', 0);

        $this->patch(route('onboarding.update'), ['action' => 'advance', 'step' => 0])
            ->assertRedirect(route('onboarding.show'));
        $this->assertDatabaseHas('user_onboarding_states', [
            'user_id' => $learner->id,
            'context_role' => 'learner',
            'status' => 'in_progress',
            'current_step' => 1,
        ]);

        $this->from(route('onboarding.show'))
            ->patch(route('onboarding.update'), ['action' => 'advance', 'step' => 0])
            ->assertRedirect(route('onboarding.show'))
            ->assertSessionHasErrors('step');

        $this->patch(route('onboarding.update'), ['action' => 'advance', 'step' => 1]);
        $this->patch(route('onboarding.update'), ['action' => 'advance', 'step' => 2])
            ->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('user_onboarding_states', ['user_id' => $learner->id, 'status' => 'completed']);

        $this->patch(route('onboarding.update'), ['action' => 'restart'])
            ->assertRedirect(route('onboarding.show'));
        $this->assertDatabaseHas('user_onboarding_states', [
            'user_id' => $learner->id,
            'status' => 'in_progress',
            'current_step' => 0,
        ]);
    }

    public function test_skip_is_explicit_role_scoped_and_records_no_behavioral_analytics(): void
    {
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $this->actingAs($learner)
            ->patch(route('onboarding.update'), ['action' => 'skip'])
            ->assertRedirect(route('dashboard'));

        $state = UserOnboardingState::query()->whereBelongsTo($learner)->sole();
        $this->assertSame('learner', $state->context_role);
        $this->assertSame('skipped', $state->status);
        $this->assertNotNull($state->skipped_at);
        $this->assertFalse(Schema::hasTable('onboarding_events'));
        $this->assertFalse(Schema::hasTable('behavior_analytics'));
    }

    public function test_each_supported_work_role_receives_a_short_role_specific_three_step_guide(): void
    {
        $service = app(OnboardingService::class);
        $request = Request::create('/getting-started');
        $expectations = [
            UserRole::Learner->value => ['Learner', 'Choose where to save progress'],
            UserRole::Supervisor->value => ['Institution Instructor', 'Review institution progress'],
            UserRole::Admin->value => ['Content Admin', 'Open the content workspace'],
            UserRole::Superadmin->value => ['System Admin', 'Confirm role and scope'],
        ];

        foreach ($expectations as $role => [$label, $firstStep]) {
            $user = User::factory()->create(['role' => $role]);
            if ($role === UserRole::Supervisor->value) {
                $this->grantInstitutionRole($user, InstitutionRole::Instructor);
            }
            $snapshot = $service->snapshot($request, $user);

            $this->assertSame($label, $snapshot['label']);
            $this->assertCount(3, $snapshot['steps']);
            $this->assertSame($firstStep, $snapshot['steps'][0]['title']);
            $this->assertSame('pending', $snapshot['status']);
        }

        $this->assertDatabaseCount('user_onboarding_states', 0);
    }
}
