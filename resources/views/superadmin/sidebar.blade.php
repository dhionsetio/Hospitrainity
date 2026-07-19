@php
    $administrationRoutePrefix = Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin';
    $canonicalExerciseRouteActive = request()->routeIs($administrationRoutePrefix.'.curriculum-exercises.*')
        || request()->routeIs($administrationRoutePrefix.'.curriculum-drafts.exercises.*');
    $canonicalContentRouteActive = request()->routeIs($administrationRoutePrefix.'.curriculum-drafts.*')
        && ! $canonicalExerciseRouteActive;
    $legacyEvidenceRouteActive = request()->routeIs($administrationRoutePrefix.'.legacy-evidence.*')
        || request()->routeIs($administrationRoutePrefix.'.modules.*')
        || request()->routeIs($administrationRoutePrefix.'.lessons.*')
        || request()->routeIs($administrationRoutePrefix.'.vocabularies.*')
        || request()->routeIs($administrationRoutePrefix.'.materials.*')
        || request()->routeIs($administrationRoutePrefix.'.exercises.*');
@endphp

<aside class="flex w-full flex-shrink-0 flex-col border-b border-neutral-200 bg-white p-4 text-neutral-600 md:w-64 md:border-b-0 md:border-r">
    <div class="mb-4 py-4 text-center">
        <a href="{{ route($administrationRoutePrefix.'.dashboard') }}" class="text-2xl font-bold text-neutral-900">Hospitrainity <span class="text-indigo-600">{{ __('admin.admin') }}</span></a>
    </div>
    <nav class="flex-grow" aria-label="{{ __('admin.administration_navigation') }}">
        <ul class="space-y-2">
            <li>
                <a href="{{ route($administrationRoutePrefix.'.dashboard') }}" class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ request()->routeIs($administrationRoutePrefix.'.dashboard') ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-tachometer-alt fa-fw" aria-hidden="true"></i>
                    <span>{{ __('Dashboard') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ route($administrationRoutePrefix.'.curriculum-drafts.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $canonicalContentRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-pen-ruler fa-fw" aria-hidden="true"></i>
                    <span>{{ __('admin.content') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ route($administrationRoutePrefix.'.curriculum-exercises.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $canonicalExerciseRouteActive ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-puzzle-piece fa-fw" aria-hidden="true"></i>
                    <span>{{ __('admin.exercises') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ route($administrationRoutePrefix.'.progress.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ request()->routeIs($administrationRoutePrefix.'.progress.*') ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-chart-line fa-fw" aria-hidden="true"></i>
                    <span>{{ __('admin.progress') }}</span>
                </a>
            </li>
            @if(Auth::user()->isSuperAdmin())
                <li>
                    <a href="{{ route('superadmin.users.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ request()->routeIs('superadmin.users.*') ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                        <i class="fas fa-users-cog fa-fw" aria-hidden="true"></i>
                        <span>{{ __('admin.users') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('superadmin.audit.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ request()->routeIs('superadmin.audit.*') ? 'bg-indigo-600 font-semibold text-white' : 'hover:bg-neutral-100' }}">
                        <i class="fas fa-clipboard-list fa-fw" aria-hidden="true"></i>
                        <span>{{ __('admin.audit') }}</span>
                    </a>
                </li>
            @endif
            <li class="pt-3">
                <a href="{{ route($administrationRoutePrefix.'.legacy-evidence.index') }}" class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $legacyEvidenceRouteActive ? 'bg-amber-100 font-semibold text-amber-950' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-box-archive fa-fw" aria-hidden="true"></i>
                    <span>{{ __('admin.legacy_evidence') }}</span>
                    <span class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-950">{{ __('admin.read_only') }}</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="mt-4 border-t border-neutral-200 pt-4 md:mt-auto">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
                <i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i>
                <span>{{ __('Logout') }}</span>
            </button>
        </form>
    </div>
</aside>
