<?php

namespace App\Services;

use App\Enums\WorkContextRole;
use App\Models\User;
use App\Models\UserOnboardingState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OnboardingService
{
    public function __construct(private readonly WorkContext $workContext) {}

    /** @return array{role: WorkContextRole, label: string, status: string, current_step: int, steps: list<array<string, string>>, state: UserOnboardingState|null} */
    public function snapshot(Request $request, User $user): array
    {
        $role = $this->workContext->current($request, $user);
        $steps = $this->steps($role);
        $state = UserOnboardingState::query()
            ->where('user_id', $user->getKey())
            ->where('context_role', $role->value)
            ->first();

        return [
            'role' => $role,
            'label' => $this->label($role),
            'status' => $state === null ? 'pending' : $state->status,
            'current_step' => min($state === null ? 0 : $state->current_step, max(count($steps) - 1, 0)),
            'steps' => $steps,
            'state' => $state,
        ];
    }

    public function advance(Request $request, User $user, int $expectedStep): UserOnboardingState
    {
        $snapshot = $this->snapshot($request, $user);
        $stepCount = count($snapshot['steps']);

        return DB::transaction(function () use ($user, $snapshot, $expectedStep, $stepCount): UserOnboardingState {
            $state = $this->lockedState($user, $snapshot['role']);
            $current = $state->current_step;
            if ($expectedStep !== $current || in_array($state->status, ['completed', 'skipped'], true)) {
                throw ValidationException::withMessages([
                    'step' => __('This onboarding step changed in another request. Review the current step and try again.'),
                ]);
            }

            $next = $current + 1;
            $complete = $next >= $stepCount;
            $state->forceFill([
                'status' => $complete ? 'completed' : 'in_progress',
                'current_step' => $complete ? max($stepCount - 1, 0) : $next,
                'started_at' => $state->started_at ?? now(),
                'completed_at' => $complete ? now() : null,
                'skipped_at' => null,
            ])->save();

            return $state;
        }, 3);
    }

    public function skip(Request $request, User $user): UserOnboardingState
    {
        $snapshot = $this->snapshot($request, $user);

        return DB::transaction(function () use ($user, $snapshot): UserOnboardingState {
            $state = $this->lockedState($user, $snapshot['role']);
            $state->forceFill([
                'status' => 'skipped',
                'started_at' => $state->started_at ?? now(),
                'skipped_at' => now(),
                'completed_at' => null,
            ])->save();

            return $state;
        }, 3);
    }

    public function restart(Request $request, User $user): UserOnboardingState
    {
        $snapshot = $this->snapshot($request, $user);

        return DB::transaction(function () use ($user, $snapshot): UserOnboardingState {
            $state = $this->lockedState($user, $snapshot['role']);
            $state->forceFill([
                'status' => 'in_progress',
                'current_step' => 0,
                'started_at' => now(),
                'restarted_at' => now(),
                'skipped_at' => null,
                'completed_at' => null,
            ])->save();

            return $state;
        }, 3);
    }

    /** @return list<array<string, string>> */
    private function steps(WorkContextRole $role): array
    {
        return match ($role) {
            WorkContextRole::Learner => [
                ['key' => 'learning-context', 'title' => __('Choose where to save progress'), 'description' => __('Choose personal learning, an institution, or a Class before starting.'), 'action' => __('Review learning choices'), 'url' => route('institution-enrollment.index')],
                ['key' => 'published-learning', 'title' => __('Open your learning'), 'description' => __('Use the dashboard to start or resume a module.'), 'action' => __('Open learning dashboard'), 'url' => route('dashboard')],
                ['key' => 'help', 'title' => __('Know where to get Help'), 'description' => __('Learn what progress means and how to recover your account.'), 'action' => __('Open learner Help'), 'url' => route('help.show', 'getting-started')],
            ],
            WorkContextRole::Instructor, WorkContextRole::InstitutionAdmin => [
                ['key' => 'team-progress', 'title' => __('Review institution progress'), 'description' => __('Choose an institution and review its learner progress.'), 'action' => __('Open team dashboard'), 'url' => route('supervisor.dashboard')],
                ['key' => 'invite', 'title' => __('Choose a learner entry path'), 'description' => __('Use an addressed invitation or a time-limited classroom code.'), 'action' => __('Open invitations'), 'url' => route('supervisor.invitations.index')],
                ['key' => 'help', 'title' => __('Review staff Help'), 'description' => __('Learn how progress, invitations, and approvals work.'), 'action' => __('Open institution staff Help'), 'url' => route('help.show', 'institution-staff')],
            ],
            WorkContextRole::ContentAuthor => [
                ['key' => 'content', 'title' => __('Open the content workspace'), 'description' => __('Create or resume learning content and preview it before review.'), 'action' => __('Open learning content'), 'url' => route('admin.curriculum-drafts.index')],
                ['key' => 'activities', 'title' => __('Review activity templates'), 'description' => __('Use only currently enabled activity templates and preview before publication.'), 'action' => __('Open exercises'), 'url' => route('admin.curriculum-exercises.index')],
                ['key' => 'evidence', 'title' => __('Learn the content workflow'), 'description' => __('See how to create, preview, and submit learning content.'), 'action' => __('Open content Help'), 'url' => route('help.show', 'content-and-evidence')],
            ],
            WorkContextRole::SystemAdmin => [
                ['key' => 'scope', 'title' => __('Confirm role and scope'), 'description' => __('Use explicit work contexts and preview banners; permissions are still checked on every request.'), 'action' => __('Review work contexts'), 'url' => route('work-context.index')],
                ['key' => 'content', 'title' => __('Review current content work'), 'description' => __('Open the canonical workspace and keep technical evidence secondary to the current task.'), 'action' => __('Open canonical content'), 'url' => route('superadmin.curriculum-drafts.index')],
                ['key' => 'help', 'title' => __('Review platform Help'), 'description' => __('Use the versioned Help and glossary before assisting another role.'), 'action' => __('Open Help'), 'url' => route('help.index')],
            ],
        };
    }

    private function label(WorkContextRole $role): string
    {
        return match ($role) {
            WorkContextRole::Learner => __('Learner'),
            WorkContextRole::Instructor, WorkContextRole::InstitutionAdmin => __('Institution Supervisor'),
            WorkContextRole::ContentAuthor => __('Content Admin'),
            WorkContextRole::SystemAdmin => __('System Admin'),
        };
    }

    private function lockedState(User $user, WorkContextRole $role): UserOnboardingState
    {
        $state = UserOnboardingState::query()
            ->where('user_id', $user->getKey())
            ->where('context_role', $role->value)
            ->lockForUpdate()
            ->first();

        if ($state !== null) {
            return $state;
        }

        return UserOnboardingState::query()->create([
            'user_id' => $user->getKey(),
            'context_role' => $role->value,
            'status' => 'pending',
            'current_step' => 0,
        ]);
    }
}
