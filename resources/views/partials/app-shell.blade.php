{{-- Hospitrainity staff application shell (supervisor, admin, superadmin).
     Learners are served by partials/learner-shell.blade.php via layouts/app.
     Disclosure structure mirrors learner-shell so resources/js/app.js can
     toggle the `hidden` attribute on each [data-shell-disclosure-panel]. --}}
@php
    $shellUser = auth()->user();
    $navigationData = app(\App\Services\ShellNavigation::class)->forUser(request(), $shellUser);
    $railDestinations = $navigationData['railDestinations'];
    $roleLabel = $navigationData['roleLabel'];
    $hasAlternativeWorkRole = $navigationData['hasAlternativeWorkRole'];

    $unreadNotificationsCount = $shellUser ? $shellUser->unreadNotifications()->count() : 0;
    $shellHomeUrl = $railDestinations[0]['url'] ?? url('/');

    $workContextService = app(\App\Services\WorkContext::class);
    $workContexts = $hasAlternativeWorkRole ? $workContextService->available(request(), $shellUser) : collect();
    $currentWorkRole = $workContextService->current(request(), $shellUser);
    $currentWorkPreview = request()->hasSession() && request()->session()->get(\App\Services\WorkContext::SESSION_PREVIEW_KEY) === true;
    $currentWorkInstitutionId = request()->hasSession() ? request()->session()->get(\App\Services\InstitutionContext::SESSION_KEY) : null;

    $workRoleLabels = [
        \App\Enums\WorkContextRole::Learner->value => __('Learner'),
        \App\Enums\WorkContextRole::Instructor->value => __('Instructor'),
        \App\Enums\WorkContextRole::InstitutionAdmin->value => __('Institution Admin'),
        \App\Enums\WorkContextRole::ContentAuthor->value => __('Content Author'),
        \App\Enums\WorkContextRole::SystemAdmin->value => __('System Admin'),
    ];
    $workRoleDescriptions = [
        \App\Enums\WorkContextRole::Learner->value => __('Study independently or with one of your institutions.'),
        \App\Enums\WorkContextRole::Instructor->value => __('Manage your Classes, learners, invitations, and progress.'),
        \App\Enums\WorkContextRole::InstitutionAdmin->value => __('Manage people and Classes for this institution.'),
        \App\Enums\WorkContextRole::ContentAuthor->value => __('Create and review shared learning content.'),
        \App\Enums\WorkContextRole::SystemAdmin->value => __('Manage Hospitrainity settings and accounts.'),
    ];
    $currentWorkContextLabel = $workRoleLabels[$currentWorkRole->value] ?? $roleLabel;
@endphp

<aside class="hsp-shell-rail" aria-label="{{ __('Primary navigation') }}">
    <a href="{{ $shellHomeUrl }}" class="hsp-shell-brand flex items-center gap-2" aria-label="{{ __('Hospitrainity home') }}">
        <x-brand-logo class="h-7 w-auto" />
    </a>

    <nav class="hsp-shell-nav" aria-label="{{ __('Main navigation') }}">
        <ul>
            @foreach($railDestinations as $destination)
                <li>
                    <a
                        href="{{ $destination['url'] }}"
                        @if($destination['active']) aria-current="page" @endif
                        @class(['hsp-shell-nav__item', 'is-current' => $destination['active']])
                    >
                        <i class="fa-solid {{ $destination['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $destination['label'] }}</span>
                        @if(isset($destination['badge']))
                            <span class="ml-auto rounded bg-neutral-200 px-1.5 py-0.5 text-[10px] font-semibold text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $destination['badge'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <p class="hsp-shell-rail__note">{{ $roleLabel }}</p>
</aside>

<header class="hsp-shell-topbar">
    <a href="{{ $shellHomeUrl }}" class="hsp-shell-topbar__brand flex items-center gap-2" aria-label="{{ __('Hospitrainity home') }}">
        <x-brand-logo class="h-7 w-auto" />
    </a>

    @if($hasAlternativeWorkRole)
        <div class="hsp-shell-disclosure hsp-shell-disclosure--context" data-shell-disclosure>
            <button
                type="button"
                class="hsp-shell-context-button"
                aria-controls="staff-work-context-panel"
                aria-expanded="false"
                data-shell-disclosure-button
                data-open-label="{{ __('Open work context') }}"
                data-close-label="{{ __('Close work context') }}"
                aria-label="{{ __('Open work context') }}"
            >
                <span class="hsp-shell-context-button__icon" aria-hidden="true"><i class="fa-solid fa-user-gear"></i></span>
                <span class="hsp-shell-context-button__copy">
                    <span>{{ __('Work context') }}</span>
                    <strong>{{ $currentWorkContextLabel }}</strong>
                </span>
                <i class="fa-solid fa-chevron-down hsp-shell-disclosure__chevron" aria-hidden="true"></i>
            </button>

            <section id="staff-work-context-panel" class="hsp-shell-disclosure__panel hsp-shell-context-panel" data-shell-disclosure-panel hidden aria-labelledby="staff-work-context-title">
                <div class="hsp-shell-panel__heading">
                    <div>
                        <p class="hsp-shell-panel__eyebrow">{{ __('Work context') }}</p>
                        <h2 id="staff-work-context-title">{{ __('Switch role or work context') }}</h2>
                    </div>
                    <button type="button" class="hsp-shell-panel__close" data-shell-disclosure-close aria-label="{{ __('Close work context') }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="hsp-shell-context-list">
                    @foreach($workContexts as $workContext)
                        @php($workRole = $workContext['role'])
                        @php($workInstitution = $workContext['institution'])
                        @php($workPreview = $workContext['preview'])
                        @php($isCurrentWorkContext = $currentWorkRole === $workRole
                            && $currentWorkPreview === $workPreview
                            && (($workInstitution?->getKey()) === $currentWorkInstitutionId || $workInstitution === null))
                        @php($workLabel = $workRoleLabels[$workRole->value] ?? $workRole->value)
                        @if($isCurrentWorkContext)
                            <div class="hsp-shell-context-option is-current" aria-current="true">
                                <span>
                                    <strong>{{ $workLabel }}</strong>
                                    <small>
                                        {{ $workRoleDescriptions[$workRole->value] ?? '' }}
                                        @if($workInstitution !== null) &middot; {{ $workInstitution->displayName(app()->getLocale()) }}@endif
                                        @if($workPreview) &middot; {{ __('Preview as role') }}@endif
                                    </small>
                                </span>
                                <span class="hsp-shell-current-mark"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('Current context') }}</span>
                            </div>
                        @else
                            <form method="POST" action="{{ route('work-context.store') }}">
                                @csrf
                                <input type="hidden" name="role" value="{{ $workRole->value }}">
                                @if($workInstitution !== null)<input type="hidden" name="institution_id" value="{{ $workInstitution->id }}">@endif
                                @if($workPreview)<input type="hidden" name="preview" value="1">@endif
                                <button type="submit" class="hsp-shell-context-option">
                                    <span>
                                        <strong>{{ $workLabel }}</strong>
                                        <small>
                                            {{ $workRoleDescriptions[$workRole->value] ?? '' }}
                                            @if($workInstitution !== null) &middot; {{ $workInstitution->displayName(app()->getLocale()) }}@endif
                                            @if($workPreview) &middot; {{ __('Preview as role') }}@endif
                                        </small>
                                    </span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </form>
                        @endif
                    @endforeach
                </div>

                <a href="{{ route('work-context.index') }}" class="hsp-shell-panel__footer-link">
                    <span>{{ __('View all role and context options') }}</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </section>
        </div>
    @endif

    <div class="flex items-center gap-3 justify-self-end">
        {{-- Notification bell disclosure --}}
        <div class="hsp-shell-disclosure hsp-shell-disclosure--notifications relative" data-shell-disclosure>
            <button
                type="button"
                class="hsp-touch-target relative inline-flex items-center justify-center rounded-lg border border-neutral-300 bg-white px-3 py-2 text-neutral-700 shadow-sm hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200"
                data-shell-disclosure-button
                data-notification-toggle
                data-open-label="{{ __('Open notifications') }}"
                data-close-label="{{ __('Close notifications') }}"
                aria-expanded="false"
                aria-controls="hsp-notifications-panel"
                aria-label="{{ __('Notifications (:count unread)', ['count' => $unreadNotificationsCount]) }}"
            >
                <i class="fa-solid fa-bell text-sm" aria-hidden="true"></i>
                @if($unreadNotificationsCount > 0)
                    <span data-notification-badge class="absolute -top-1 -right-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white shadow-sm">
                        {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                    </span>
                @endif
            </button>

            <div
                id="hsp-notifications-panel"
                class="absolute right-0 top-full z-50 mt-2 w-80 sm:w-96 rounded-xl border border-neutral-200 bg-white p-4 shadow-xl dark:border-neutral-700 dark:bg-neutral-800"
                data-shell-disclosure-panel
                hidden
            >
                <div class="flex items-center justify-between border-b border-neutral-200 pb-3 dark:border-neutral-700">
                    <h2 class="text-sm font-bold text-neutral-900 dark:text-white">{{ __('Notifications') }}</h2>
                    <div class="flex items-center gap-2">
                        <button type="button" data-notification-recheck class="text-xs text-indigo-700 hover:underline dark:text-indigo-400">
                            <i class="fa-solid fa-rotate-right mr-1" aria-hidden="true"></i>{{ __('Refresh') }}
                        </button>
                        <form method="POST" action="{{ route('notifications.readAll') }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-xs text-neutral-600 hover:underline dark:text-neutral-400">{{ __('Read all') }}</button>
                        </form>
                        <button type="button" data-shell-disclosure-close class="text-neutral-500 hover:text-neutral-700" aria-label="{{ __('Close') }}">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div data-notification-list role="status" aria-live="polite" class="mt-3 max-h-72 space-y-2 overflow-y-auto">
                    <p class="text-center text-xs text-neutral-500 py-4">{{ __('Loading notifications...') }}</p>
                </div>

                <div class="mt-3 border-t border-neutral-200 pt-2 text-center dark:border-neutral-700">
                    <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-indigo-700 hover:underline dark:text-indigo-400">
                        {{ __('View all notifications') }} &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Account disclosure --}}
        <div class="hsp-shell-disclosure hsp-shell-disclosure--account" data-shell-disclosure>
            <button
                type="button"
                class="hsp-shell-account-button"
                aria-controls="hsp-shell-account-panel"
                aria-expanded="false"
                data-shell-disclosure-button
                data-open-label="{{ __('Open account') }}"
                data-close-label="{{ __('Close account') }}"
                aria-label="{{ __('Open account') }}"
            >
                <span aria-hidden="true">{{ mb_strtoupper(mb_substr($shellUser->name ?? 'U', 0, 1)) }}</span>
                <span class="hsp-shell-account-button__copy">
                    <strong>{{ $shellUser->name }}</strong>
                    <small>{{ $roleLabel }}</small>
                </span>
                <i class="fa-solid fa-chevron-down hsp-shell-disclosure__chevron" aria-hidden="true"></i>
            </button>

            <div id="hsp-shell-account-panel" class="hsp-shell-disclosure__panel hsp-shell-account-panel" data-shell-disclosure-panel hidden aria-labelledby="staff-account-title">
                <div class="hsp-shell-panel__heading">
                    <div>
                        <p class="hsp-shell-panel__eyebrow">{{ __('Account') }}</p>
                        <h2 id="staff-account-title">{{ $shellUser->name }}</h2>
                        <p>{{ $roleLabel }}</p>
                    </div>
                    <button type="button" class="hsp-shell-panel__close" data-shell-disclosure-close aria-label="{{ __('Close account') }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <nav aria-label="{{ __('Account and preferences') }}">
                    <ul class="hsp-shell-account-links">
                        <li><a href="{{ route('search.index') }}"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>{{ __('Search') }}</span></a></li>
                        <li><a href="{{ route('onboarding.show') }}"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>{{ __('Getting started') }}</span></a></li>
                        @if($hasAlternativeWorkRole)
                            <li><a href="{{ route('work-context.index') }}"><i class="fa-solid fa-repeat" aria-hidden="true"></i><span>{{ __('Switch role') }}</span></a></li>
                        @endif
                        <li><a href="{{ route('preferences.edit') }}"><i class="fa-solid fa-sliders" aria-hidden="true"></i><span>{{ __('Display preferences') }}</span></a></li>
                        <li><a href="{{ route('security.index') }}"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>{{ __('Account security') }}</span></a></li>
                        <li><a href="{{ route('help.index') }}"><i class="fa-solid fa-circle-question" aria-hidden="true"></i><span>{{ __('Help') }}</span></a></li>
                    </ul>
                </nav>

                <div class="hsp-shell-language">
                    <p>{{ __('Language') }}</p>
                    <x-language-switcher compact />
                </div>

                <div class="hsp-shell-language border-t border-neutral-200 pt-3 mt-3 dark:border-neutral-700">
                    <p class="text-xs font-bold uppercase tracking-wider text-neutral-500 mb-2 dark:text-neutral-400">{{ __('Appearance') }}</p>
                    <button type="button" data-theme-toggle data-label-dark="{{ __('Dark Mode') }}" data-label-light="{{ __('Light Mode') }}" class="inline-flex min-h-10 w-full items-center justify-between rounded-lg border border-neutral-200 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700">
                        <span class="inline-flex items-center gap-2">
                            <i data-theme-icon class="fas fa-moon fa-fw text-neutral-600 dark:text-amber-400" aria-hidden="true"></i>
                            <span data-theme-label>{{ __('Dark Mode') }}</span>
                        </span>
                        <span class="text-[10px] text-neutral-400 dark:text-neutral-500"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
                    </button>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="hsp-shell-logout" data-confirm-submit="{{ __('Are you sure you want to sign out?') }}">
                    @csrf
                    <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>{{ __('Logout') }}</span></button>
                </form>
            </div>
        </div>
    </div>
</header>

<nav class="hsp-shell-bottom-nav" aria-label="{{ __('Mobile navigation') }}">
    <ul>
        @foreach($railDestinations as $destination)
            <li>
                <a
                    href="{{ $destination['url'] }}"
                    @if($destination['active']) aria-current="page" @endif
                    @class(['hsp-shell-bottom-nav__item', 'is-current' => $destination['active']])
                >
                    <i class="fa-solid {{ $destination['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $destination['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
