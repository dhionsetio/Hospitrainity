@php
    $administrationRoutePrefix = Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin';
    $administrationDashboardRouteActive = request()->routeIs($administrationRoutePrefix.'.dashboard');
    $canonicalExerciseRouteActive = request()->routeIs($administrationRoutePrefix.'.curriculum-exercises.*')
        || request()->routeIs($administrationRoutePrefix.'.curriculum-drafts.exercises.*');
    $canonicalContentRouteActive = request()->routeIs($administrationRoutePrefix.'.curriculum-drafts.*')
        && ! $canonicalExerciseRouteActive;
    $administrationProgressRouteActive = request()->routeIs($administrationRoutePrefix.'.progress.*');
    $administrationInvitationsRouteActive = request()->routeIs($administrationRoutePrefix.'.invitations.*');
    $administrationJoinCodesRouteActive = request()->routeIs($administrationRoutePrefix.'.join-codes.*')
        || request()->routeIs($administrationRoutePrefix.'.join-requests.*');
    $userAdministrationRouteActive = request()->routeIs('superadmin.users.*');
    $institutionRouteActive = request()->routeIs('superadmin.institutions.*');
    $auditRouteActive = request()->routeIs('superadmin.audit.*');
    $legacyEvidenceRouteActive = request()->routeIs($administrationRoutePrefix.'.legacy-evidence.*')
        || request()->routeIs($administrationRoutePrefix.'.modules.*')
        || request()->routeIs($administrationRoutePrefix.'.lessons.*')
        || request()->routeIs($administrationRoutePrefix.'.vocabularies.*')
        || request()->routeIs($administrationRoutePrefix.'.materials.*')
        || request()->routeIs($administrationRoutePrefix.'.exercises.*');
    $mobileAdministrationItems = [
        ['url' => route($administrationRoutePrefix.'.dashboard'), 'label' => __('Dashboard'), 'icon' => 'fa-tachometer-alt', 'active' => $administrationDashboardRouteActive],
        ['url' => route($administrationRoutePrefix.'.curriculum-drafts.index'), 'label' => __('admin.content'), 'icon' => 'fa-pen-ruler', 'active' => $canonicalContentRouteActive],
        ['url' => route($administrationRoutePrefix.'.curriculum-exercises.index'), 'label' => __('admin.exercises'), 'icon' => 'fa-puzzle-piece', 'active' => $canonicalExerciseRouteActive],
        ['url' => route($administrationRoutePrefix.'.progress.index'), 'label' => __('admin.progress'), 'icon' => 'fa-chart-line', 'active' => $administrationProgressRouteActive],
    ];
    if (Auth::user()->isSuperAdmin()) {
        $mobileAdministrationItems[] = ['url' => route('superadmin.institutions.index'), 'label' => __('Institutions'), 'icon' => 'fa-building', 'active' => $institutionRouteActive];
        $mobileAdministrationItems[] = ['url' => route('superadmin.invitations.index'), 'label' => __('Invitations'), 'icon' => 'fa-envelope-open-text', 'active' => $administrationInvitationsRouteActive];
        $mobileAdministrationItems[] = ['url' => route('superadmin.join-codes.index'), 'label' => __('Classroom codes'), 'icon' => 'fa-key', 'active' => $administrationJoinCodesRouteActive];
        $mobileAdministrationItems[] = ['url' => route('superadmin.users.index'), 'label' => __('admin.users'), 'icon' => 'fa-users-cog', 'active' => $userAdministrationRouteActive];
        $mobileAdministrationItems[] = ['url' => route('superadmin.audit.index'), 'label' => __('admin.audit'), 'icon' => 'fa-clipboard-list', 'active' => $auditRouteActive];
    }
    if (Auth::user()->isSuperAdmin()) {
        $mobileAdministrationItems[] = ['url' => route('superadmin.legacy-evidence.index'), 'label' => __('admin.legacy_evidence'), 'icon' => 'fa-box-archive', 'active' => $legacyEvidenceRouteActive, 'badge' => __('admin.read_only')];
    }
    $administrationRoleLabel = Auth::user()->isSuperAdmin() ? __('System Admin') : __('Content Admin');
@endphp

@include('partials.mobile-role-navigation', [
    'drawerId' => 'administration-navigation-drawer',
    'roleLabel' => $administrationRoleLabel,
    'contextLabel' => Auth::user()->isSuperAdmin() ? __('System-wide') : __('Content workspace'),
    'navigationLabel' => __('admin.administration_navigation'),
    'navigationItems' => $mobileAdministrationItems,
    'hasAlternativeRole' => app(\App\Services\WorkContext::class)->hasAlternativeRole(Auth::user()),
])

<aside class="hidden w-full flex-shrink-0 flex-col border-b border-neutral-200 bg-white p-4 text-neutral-600 md:sticky md:top-0 md:flex md:h-dvh md:w-64 md:self-start md:overflow-y-auto md:border-b-0 md:border-r">
    <div class="mb-4 py-4 text-center">
        <a href="{{ route($administrationRoutePrefix.'.dashboard') }}" class="inline-flex items-center gap-2" aria-label="{{ __('Hospitrainity admin dashboard') }}">
            <x-brand-logo class="h-8 w-auto" />
            <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2 py-1 rounded">{{ __('admin.admin') }}</span>
        </a>
    </div>
    <nav class="flex-grow" aria-label="{{ __('admin.administration_navigation') }}">
        <ul class="space-y-2">
            <li>
                <a href="{{ route($administrationRoutePrefix.'.dashboard') }}" @if($administrationDashboardRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $administrationDashboardRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-tachometer-alt fa-fw" aria-hidden="true"></i>
                    <span>{{ __('Dashboard') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ route($administrationRoutePrefix.'.curriculum-drafts.index') }}" @if($canonicalContentRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $canonicalContentRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-pen-ruler fa-fw" aria-hidden="true"></i>
                    <span>{{ __('admin.content') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ route($administrationRoutePrefix.'.curriculum-exercises.index') }}" @if($canonicalExerciseRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $canonicalExerciseRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-puzzle-piece fa-fw" aria-hidden="true"></i>
                    <span>{{ __('admin.exercises') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ route($administrationRoutePrefix.'.progress.index') }}" @if($administrationProgressRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $administrationProgressRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-chart-line fa-fw" aria-hidden="true"></i>
                    <span>{{ __('admin.progress') }}</span>
                </a>
            </li>
            @if(Auth::user()->isSuperAdmin())
                <li>
                    <a href="{{ route('superadmin.institutions.index') }}" @if($institutionRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $institutionRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                        <i class="fas fa-building fa-fw" aria-hidden="true"></i>
                        <span>{{ __('Institutions') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('superadmin.invitations.index') }}" @if($administrationInvitationsRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $administrationInvitationsRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                        <i class="fas fa-envelope-open-text fa-fw" aria-hidden="true"></i>
                        <span>{{ __('Invitations') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('superadmin.join-codes.index') }}" @if($administrationJoinCodesRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $administrationJoinCodesRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                        <i class="fas fa-key fa-fw" aria-hidden="true"></i>
                        <span>{{ __('Classroom codes') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('superadmin.users.index') }}" @if($userAdministrationRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $userAdministrationRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                        <i class="fas fa-users-cog fa-fw" aria-hidden="true"></i>
                        <span>{{ __('admin.users') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('superadmin.audit.index') }}" @if($auditRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $auditRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                        <i class="fas fa-clipboard-list fa-fw" aria-hidden="true"></i>
                        <span>{{ __('admin.audit') }}</span>
                    </a>
                </li>
            @endif
            @if(Auth::user()->isSuperAdmin())
            <li class="pt-3">
                <a href="{{ route($administrationRoutePrefix.'.legacy-evidence.index') }}" @if($legacyEvidenceRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $legacyEvidenceRouteActive ? 'bg-amber-100 font-semibold text-amber-950' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-box-archive fa-fw" aria-hidden="true"></i>
                    <span>{{ __('admin.legacy_evidence') }}</span>
                    <span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-950">{{ __('admin.read_only') }}</span>
                </a>
            </li>
            @endif
        </ul>
    </nav>
    <div class="mt-4 border-t border-neutral-200 pt-4 md:mt-auto">
        <a href="{{ route('search.index') }}" class="mb-2 flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
            <i class="fas fa-magnifying-glass fa-fw" aria-hidden="true"></i><span>{{ __('Search') }}</span>
        </a>
        <details class="hsp-staff-account-disclosure">
            <summary class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-4 py-2 font-semibold hover:bg-neutral-100">
                <i class="fas fa-circle-user fa-fw" aria-hidden="true"></i>
                <span>{{ __('Account and help') }}</span>
                <i class="fas fa-chevron-down fa-fw ml-auto text-xs hsp-staff-account-disclosure__chevron" aria-hidden="true"></i>
            </summary>
            <nav class="mt-2 space-y-1 border-l border-neutral-200 pl-2" aria-label="{{ __('Account and help') }}">
                <a href="{{ route('onboarding.show') }}" class="flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
                    <i class="fas fa-route fa-fw" aria-hidden="true"></i><span>{{ __('Getting started') }}</span>
                </a>
                <a href="{{ route('preferences.edit') }}" class="flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
                    <i class="fas fa-universal-access fa-fw" aria-hidden="true"></i><span>{{ __('Display preferences') }}</span>
                </a>
                <a href="{{ route('security.index') }}" class="flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
                    <i class="fas fa-shield-halved fa-fw" aria-hidden="true"></i><span>{{ __('Account security') }}</span>
                </a>
                @if(app(\App\Services\WorkContext::class)->hasAlternativeRole(Auth::user()))
                    <a href="{{ route('work-context.index') }}" class="flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
                        <i class="fas fa-repeat fa-fw" aria-hidden="true"></i>
                        <span>{{ __('Switch role') }}</span>
                    </a>
                @endif
                <a href="{{ route('help.index') }}" class="flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
                    <i class="fas fa-circle-question fa-fw" aria-hidden="true"></i><span>{{ __('Help') }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}" data-confirm-submit="{{ __('Are you sure you want to sign out?') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
                        <i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i>
                        <span>{{ __('Logout') }}</span>
                    </button>
                </form>
            </nav>
        </details>
    </div>
</aside>
