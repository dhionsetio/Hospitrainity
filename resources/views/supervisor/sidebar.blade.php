@php
    $supervisorDashboardRouteActive = request()->routeIs('supervisor.dashboard') || request()->routeIs('supervisor.progress.*');
    $supervisorInvitationsRouteActive = request()->routeIs('supervisor.invitations.*');
    $supervisorJoinCodesRouteActive = request()->routeIs('supervisor.join-codes.*') || request()->routeIs('supervisor.join-requests.*');
@endphp

<aside class="flex w-full flex-shrink-0 flex-col border-b border-neutral-200 bg-white p-4 text-neutral-600 md:w-64 md:border-b-0 md:border-r">
    <div class="mb-4 py-4 text-center">
        <a href="{{ route('supervisor.dashboard') }}" class="text-2xl font-bold text-neutral-900">
            Hospitrainity <span class="text-indigo-600">{{ __('admin.supervisor') }}</span>
        </a>
    </div>
    <nav class="flex-grow" aria-label="{{ __('admin.supervisor_navigation') }}">
        <ul class="space-y-2">
            <li>
                <a href="{{ route('supervisor.dashboard') }}" @if($supervisorDashboardRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $supervisorDashboardRouteActive ? 'bg-indigo-600 font-semibold text-white shadow-md' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-chart-line fa-fw" aria-hidden="true"></i>
                    <span>{{ __('admin.team_dashboard') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ route('supervisor.invitations.index') }}" @if($supervisorInvitationsRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $supervisorInvitationsRouteActive ? 'bg-indigo-600 font-semibold text-white shadow-md' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-envelope-open-text fa-fw" aria-hidden="true"></i>
                    <span>{{ __('Invitations') }}</span>
                </a>
            </li>
            <li>
                <a href="{{ route('supervisor.join-codes.index') }}" @if($supervisorJoinCodesRouteActive) aria-current="page" @endif class="flex items-center gap-3 rounded-lg px-4 py-2 transition-colors {{ $supervisorJoinCodesRouteActive ? 'bg-indigo-600 font-semibold text-white shadow-md' : 'hover:bg-neutral-100' }}">
                    <i class="fas fa-key fa-fw" aria-hidden="true"></i>
                    <span>{{ __('Classroom codes') }}</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="mt-4 border-t border-neutral-200 pt-4 md:mt-auto">
        @if(app(\App\Services\WorkContext::class)->hasAlternativeRole(Auth::user()))
            <a href="{{ route('work-context.index') }}" class="mb-2 flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
                <i class="fas fa-repeat fa-fw" aria-hidden="true"></i>
                <span>{{ __('Switch role') }}</span>
            </a>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-4 py-2 hover:bg-neutral-100">
                <i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i>
                <span>{{ __('Logout') }}</span>
            </button>
        </form>
    </div>
</aside>
