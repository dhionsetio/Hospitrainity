<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;

class ShellNavigation
{
    public function __construct(
        private readonly WorkContext $workContext,
    ) {}

    /**
     * Compute role-filtered shell navigation data.
     *
     * @return array{
     *     roleLabel: string,
     *     railDestinations: list<array{label: string, url: string, icon: string, active: bool}>,
     *     hasAlternativeWorkRole: bool,
     * }
     */
    public function forUser(?Request $request, ?User $user): array
    {
        if ($user === null) {
            return [
                'roleLabel' => '',
                'railDestinations' => [],
                'hasAlternativeWorkRole' => false,
            ];
        }

        $role = $user->role;
        $roleLabel = $role->label();
        $hasAlternativeWorkRole = $this->workContext->hasAlternativeRole($user);
        $route = $request ? $request->route() : null;
        $currentRouteName = $route ? (string) $route->getName() : '';

        $railDestinations = match ($role) {
            UserRole::Learner => $this->learnerDestinations($currentRouteName),
            UserRole::Supervisor => $this->supervisorDestinations($currentRouteName),
            UserRole::Admin => $this->adminDestinations($currentRouteName),
            UserRole::Superadmin => $this->superadminDestinations($currentRouteName),
        };

        return [
            'roleLabel' => $roleLabel,
            'railDestinations' => $railDestinations,
            'hasAlternativeWorkRole' => $hasAlternativeWorkRole,
        ];
    }

    /** @return list<array{label: string, url: string, icon: string, active: bool}> */
    private function learnerDestinations(string $currentRouteName): array
    {
        return [
            [
                'label' => __('Home'),
                'url' => route('dashboard'),
                'icon' => 'fa-house',
                'active' => $currentRouteName === 'dashboard',
            ],
            [
                'label' => __('Learn'),
                'url' => route('dashboard').'#learning-modules',
                'icon' => 'fa-book-open',
                'active' => (str_starts_with($currentRouteName, 'curriculum.') && $currentRouteName !== 'curriculum.confidence-history')
                    || str_starts_with($currentRouteName, 'modules.')
                    || str_starts_with($currentRouteName, 'lessons.')
                    || str_starts_with($currentRouteName, 'assistant.')
                    || str_starts_with($currentRouteName, 'responses.'),
            ],
            [
                'label' => __('Progress'),
                'url' => route('curriculum.confidence-history'),
                'icon' => 'fa-chart-line',
                'active' => $currentRouteName === 'curriculum.confidence-history',
            ],
            [
                'label' => __('Help'),
                'url' => route('help.index'),
                'icon' => 'fa-circle-question',
                'active' => str_starts_with($currentRouteName, 'help.') || $currentRouteName === 'glossary.index',
            ],
        ];
    }

    /** @return list<array{label: string, url: string, icon: string, active: bool}> */
    private function supervisorDestinations(string $currentRouteName): array
    {
        return [
            [
                'label' => __('Dashboard'),
                'url' => route('supervisor.dashboard'),
                'icon' => 'fa-gauge-high',
                'active' => $currentRouteName === 'supervisor.dashboard',
            ],
            [
                'label' => __('classes.index_title'),
                'url' => route('supervisor.classes.index'),
                'icon' => 'fa-chalkboard-user',
                'active' => str_starts_with($currentRouteName, 'supervisor.classes.'),
            ],
            [
                'label' => __('Invitations'),
                'url' => route('supervisor.invitations.index'),
                'icon' => 'fa-envelope-open-text',
                'active' => $currentRouteName === 'supervisor.invitations.index',
            ],
            [
                'label' => __('Classroom codes'),
                'url' => route('supervisor.join-codes.index'),
                'icon' => 'fa-key',
                'active' => $currentRouteName === 'supervisor.join-codes.index',
            ],
            [
                'label' => __('Help'),
                'url' => route('help.index'),
                'icon' => 'fa-circle-question',
                'active' => str_starts_with($currentRouteName, 'help.') || $currentRouteName === 'glossary.index',
            ],
        ];
    }

    /** @return list<array{label: string, url: string, icon: string, active: bool}> */
    private function adminDestinations(string $currentRouteName): array
    {
        return [
            [
                'label' => __('Dashboard'),
                'url' => route('admin.dashboard'),
                'icon' => 'fa-gauge-high',
                'active' => $currentRouteName === 'admin.dashboard',
            ],
            [
                'label' => __('Progress'),
                'url' => route('admin.progress.index'),
                'icon' => 'fa-chart-line',
                'active' => str_starts_with($currentRouteName, 'admin.progress.'),
            ],
            [
                'label' => __('Content'),
                'url' => route('admin.curriculum-drafts.index'),
                'icon' => 'fa-layer-group',
                'active' => str_starts_with($currentRouteName, 'admin.modules.') || str_starts_with($currentRouteName, 'admin.lessons.') || str_starts_with($currentRouteName, 'admin.vocabularies.') || str_starts_with($currentRouteName, 'admin.materials.') || str_starts_with($currentRouteName, 'admin.curriculum-drafts.'),
            ],
            [
                'label' => __('Exercises'),
                'url' => route('admin.curriculum-exercises.index'),
                'icon' => 'fa-dumbbell',
                'active' => str_starts_with($currentRouteName, 'admin.exercises.') || str_starts_with($currentRouteName, 'admin.curriculum-exercises.'),
            ],
            [
                'label' => __('Help'),
                'url' => route('help.index'),
                'icon' => 'fa-circle-question',
                'active' => str_starts_with($currentRouteName, 'help.') || $currentRouteName === 'glossary.index',
            ],
        ];
    }

    /** @return list<array{label: string, url: string, icon: string, active: bool}> */
    private function superadminDestinations(string $currentRouteName): array
    {
        return [
            [
                'label' => __('Dashboard'),
                'url' => route('superadmin.dashboard'),
                'icon' => 'fa-gauge-high',
                'active' => $currentRouteName === 'superadmin.dashboard',
            ],
            [
                'label' => __('Progress'),
                'url' => route('superadmin.progress.index'),
                'icon' => 'fa-chart-line',
                'active' => str_starts_with($currentRouteName, 'superadmin.progress.'),
            ],
            [
                'label' => __('Content'),
                'url' => route('superadmin.curriculum-drafts.index'),
                'icon' => 'fa-layer-group',
                'active' => str_starts_with($currentRouteName, 'superadmin.modules.') || str_starts_with($currentRouteName, 'superadmin.lessons.') || str_starts_with($currentRouteName, 'superadmin.vocabularies.') || str_starts_with($currentRouteName, 'superadmin.materials.') || str_starts_with($currentRouteName, 'superadmin.curriculum-drafts.'),
            ],
            [
                'label' => __('Exercises'),
                'url' => route('superadmin.curriculum-exercises.index'),
                'icon' => 'fa-dumbbell',
                'active' => str_starts_with($currentRouteName, 'superadmin.exercises.') || str_starts_with($currentRouteName, 'superadmin.curriculum-exercises.'),
            ],
            [
                'label' => __('Institutions'),
                'url' => route('superadmin.institutions.index'),
                'icon' => 'fa-building-columns',
                'active' => str_starts_with($currentRouteName, 'superadmin.institutions.'),
            ],
            [
                'label' => __('Invitations'),
                'url' => route('superadmin.invitations.index'),
                'icon' => 'fa-paper-plane',
                'active' => $currentRouteName === 'superadmin.invitations.index',
            ],
            [
                'label' => __('Classroom codes'),
                'url' => route('superadmin.join-codes.index'),
                'icon' => 'fa-key',
                'active' => $currentRouteName === 'superadmin.join-codes.index',
            ],
            [
                'label' => __('Users'),
                'url' => route('superadmin.users.index'),
                'icon' => 'fa-users',
                'active' => str_starts_with($currentRouteName, 'superadmin.users.'),
            ],
            [
                'label' => __('Audit logs'),
                'url' => route('superadmin.audit.index'),
                'icon' => 'fa-shield-halved',
                'active' => str_starts_with($currentRouteName, 'superadmin.audit.'),
            ],
            [
                'label' => __('admin.legacy_evidence'),
                'url' => route('superadmin.legacy-evidence.index'),
                'icon' => 'fa-file-invoice',
                'active' => str_starts_with($currentRouteName, 'superadmin.legacy-evidence.'),
                'badge' => __('Read-only'),
            ],
            [
                'label' => __('Help'),
                'url' => route('help.index'),
                'icon' => 'fa-circle-question',
                'active' => str_starts_with($currentRouteName, 'help.') || $currentRouteName === 'glossary.index',
            ],
        ];
    }
}
