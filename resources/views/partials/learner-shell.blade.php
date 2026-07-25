@php
    $shellUser = auth()->user();
    $learningContextService = app(\App\Services\LearningContext::class);
    $learningMemberships = $learningContextService->availableMemberships($shellUser);
    $learningClassEnrollments = $learningContextService->availableClassEnrollments($shellUser);
    $learningContext = $learningContextService->current(request(), $shellUser);
    $currentLearningMembership = $learningMemberships->firstWhere('id', $learningContext['membership_id']);
    $currentLearningClass = $learningClassEnrollments->firstWhere('id', $learningContext['course_enrollment_id']);
    $currentLearningContextLabel = $currentLearningClass?->offering?->title
        ?? $currentLearningMembership?->institution->displayName(app()->getLocale())
        ?? __('Personal self-study');
    $hasAlternativeWorkRole = app(\App\Services\WorkContext::class)->hasAlternativeRole($shellUser);

    $learnerDestinations = [
        [
            'label' => __('Home'),
            'url' => route('dashboard'),
            'icon' => 'fa-house',
            'active' => request()->routeIs('dashboard'),
        ],
        [
            'label' => __('Learn'),
            'url' => route('dashboard').'#learning-modules',
            'icon' => 'fa-book-open',
            'active' => request()->routeIs(
                'modules.*',
                'lessons.*',
                'curriculum.chapters.*',
                'curriculum.sections.*',
                'curriculum.activities.*',
                'curriculum.steps.*',
                'assistant.*',
                'responses.*',
            ),
        ],
        [
            'label' => __('Progress'),
            'url' => route('curriculum.confidence-history'),
            'icon' => 'fa-chart-line',
            'active' => request()->routeIs('curriculum.confidence-history'),
        ],
        [
            'label' => __('Help'),
            'url' => route('help.index'),
            'icon' => 'fa-circle-question',
            'active' => request()->routeIs('help.*', 'glossary.index'),
        ],
    ];
@endphp

<aside class="hsp-shell-rail" aria-label="{{ __('Learner navigation') }}">
    <a href="{{ route('dashboard') }}" class="hsp-shell-brand flex items-center gap-2" aria-label="{{ __('Hospitrainity learning dashboard') }}">
        <x-brand-logo class="h-7 w-auto" />
    </a>

    <nav class="hsp-shell-nav" aria-label="{{ __('Primary learner navigation') }}">
        <ul>
            @foreach($learnerDestinations as $destination)
                <li>
                    <a
                        href="{{ $destination['url'] }}"
                        @if($destination['active']) aria-current="page" @endif
                        @class(['hsp-shell-nav__item', 'is-current' => $destination['active']])
                    >
                        <i class="fa-solid {{ $destination['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $destination['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <p class="hsp-shell-rail__note">{{ __('Hospitality English learning') }}</p>
</aside>

<header class="hsp-shell-topbar">
    <a href="{{ route('dashboard') }}" class="hsp-shell-topbar__brand flex items-center gap-2" aria-label="{{ __('Hospitrainity learning dashboard') }}">
        <x-brand-logo class="h-7 w-auto" />
    </a>

    <div class="hsp-shell-disclosure hsp-shell-disclosure--context" data-shell-disclosure>
        <button
            type="button"
            class="hsp-shell-context-button"
            aria-controls="learner-context-panel"
            aria-expanded="false"
            data-shell-disclosure-button
            data-open-label="{{ __('Open learning context') }}"
            data-close-label="{{ __('Close learning context') }}"
            aria-label="{{ __('Open learning context') }}"
        >
            <span class="hsp-shell-context-button__icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
            <span class="hsp-shell-context-button__copy">
                <span>{{ __('Learning context') }}</span>
                <strong>{{ $currentLearningContextLabel }}</strong>
            </span>
            <i class="fa-solid fa-chevron-down hsp-shell-disclosure__chevron" aria-hidden="true"></i>
        </button>

        <section id="learner-context-panel" class="hsp-shell-disclosure__panel hsp-shell-context-panel" data-shell-disclosure-panel hidden aria-labelledby="learner-context-title">
            <div class="hsp-shell-panel__heading">
                <div>
                    <p class="hsp-shell-panel__eyebrow">{{ __('Learning context') }}</p>
                    <h2 id="learner-context-title">{{ __('Choose where new progress is recorded') }}</h2>
                </div>
                <button type="button" class="hsp-shell-panel__close" data-shell-disclosure-close aria-label="{{ __('Close learning context') }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="hsp-shell-context-list">
                @if($learningContext['membership_id'] === null && !$learningContext['class_invalidated'])
                    <div class="hsp-shell-context-option is-current" aria-current="true">
                        <span>
                            <strong>{{ __('Personal self-study') }}</strong>
                            <small>{{ __('Visible to you, not institution staff.') }}</small>
                        </span>
                        <span class="hsp-shell-current-mark"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('Current context') }}</span>
                    </div>
                @else
                    <form method="POST" action="{{ route('learning-context.select') }}">
                        @csrf
                        <input type="hidden" name="scope" value="personal">
                        <button type="submit" class="hsp-shell-context-option">
                            <span>
                                <strong>{{ __('Personal self-study') }}</strong>
                                <small>{{ __('Visible to you, not institution staff.') }}</small>
                            </span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>
                @endif

                @foreach($learningMemberships as $membership)
                    @php($isCurrentLearningContext = $learningContext['course_enrollment_id'] === null && $learningContext['membership_id'] === $membership->id)
                    @if($isCurrentLearningContext)
                        <div class="hsp-shell-context-option is-current" aria-current="true">
                            <span>
                                <strong>{{ $membership->institution->displayName(app()->getLocale()) }}</strong>
                                <small>{{ __('Visible to authorized staff from this institution.') }}</small>
                            </span>
                            <span class="hsp-shell-current-mark"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('Current context') }}</span>
                        </div>
                    @else
                        <form method="POST" action="{{ route('learning-context.select') }}">
                            @csrf
                            <input type="hidden" name="scope" value="institution">
                            <input type="hidden" name="membership_id" value="{{ $membership->id }}">
                            <button type="submit" class="hsp-shell-context-option">
                                <span>
                                    <strong>{{ $membership->institution->displayName(app()->getLocale()) }}</strong>
                                    <small>{{ __('Visible to authorized staff from this institution.') }}</small>
                                </span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </form>
                    @endif
                @endforeach

                @foreach($learningClassEnrollments as $enrollment)
                    @php($isCurrentLearningContext = $learningContext['course_enrollment_id'] === $enrollment->id)
                    @if($isCurrentLearningContext)
                        <div class="hsp-shell-context-option is-current" aria-current="true">
                            <span>
                                <strong>{{ $enrollment->offering->title }}</strong>
                                <small>{{ $enrollment->membership->institution->displayName(app()->getLocale()) }} · {{ __('Progress is attached to this Class.') }}</small>
                            </span>
                            <span class="hsp-shell-current-mark"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('Current context') }}</span>
                        </div>
                    @else
                        <form method="POST" action="{{ route('learning-context.select') }}">
                            @csrf
                            <input type="hidden" name="scope" value="class">
                            <input type="hidden" name="course_enrollment_id" value="{{ $enrollment->id }}">
                            <button type="submit" class="hsp-shell-context-option">
                                <span>
                                    <strong>{{ $enrollment->offering->title }}</strong>
                                    <small>{{ $enrollment->membership->institution->displayName(app()->getLocale()) }} · {{ __('Progress is attached to this Class.') }}</small>
                                </span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>

            <a href="{{ route('institution-enrollment.index') }}" class="hsp-shell-panel__footer-link">
                <span>{{ __('Manage institution connections') }}</span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </section>
    </div>

    <div class="hsp-shell-disclosure hsp-shell-disclosure--account" data-shell-disclosure>
        <button
            type="button"
            class="hsp-shell-account-button"
            aria-controls="learner-account-panel"
            aria-expanded="false"
            data-shell-disclosure-button
            data-open-label="{{ __('Open account') }}"
            data-close-label="{{ __('Close account') }}"
            aria-label="{{ __('Open account') }}"
        >
            <span aria-hidden="true">{{ mb_strtoupper(mb_substr($shellUser->name ?? 'L', 0, 1)) }}</span>
            <span class="hsp-shell-account-button__copy">
                <strong>{{ $shellUser->name }}</strong>
                <small>{{ __('Learner') }}</small>
            </span>
            <i class="fa-solid fa-chevron-down hsp-shell-disclosure__chevron" aria-hidden="true"></i>
        </button>

        <section id="learner-account-panel" class="hsp-shell-disclosure__panel hsp-shell-account-panel" data-shell-disclosure-panel hidden aria-labelledby="learner-account-title">
            <div class="hsp-shell-panel__heading">
                <div>
                    <p class="hsp-shell-panel__eyebrow">{{ __('Account') }}</p>
                    <h2 id="learner-account-title">{{ $shellUser->name }}</h2>
                    <p>{{ __('Learner') }}</p>
                </div>
                <button type="button" class="hsp-shell-panel__close" data-shell-disclosure-close aria-label="{{ __('Close account') }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <nav aria-label="{{ __('Account and preferences') }}">
                <ul class="hsp-shell-account-links">
                    <li><a href="{{ route('search.index') }}"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>{{ __('Search') }}</span></a></li>
                    <li><a href="{{ route('assistant.index') }}"><i class="fa-solid fa-comments" aria-hidden="true"></i><span>{{ __('engagement.assistant') }}</span></a></li>
                    <li><a href="{{ route('responses.index') }}"><i class="fa-solid fa-file-pen" aria-hidden="true"></i><span>{{ __('engagement.saved_writing') }}</span></a></li>
                    <li><a href="{{ route('onboarding.show') }}"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>{{ __('Getting started') }}</span></a></li>
                    <li><a href="{{ route('institution-enrollment.index') }}"><i class="fa-solid fa-building-columns" aria-hidden="true"></i><span>{{ __('Institution connections') }}</span></a></li>
                    @if($hasAlternativeWorkRole)
                        <li><a href="{{ route('work-context.index') }}"><i class="fa-solid fa-repeat" aria-hidden="true"></i><span>{{ __('Switch role') }}</span></a></li>
                    @endif
                    <li><a href="{{ route('preferences.edit') }}"><i class="fa-solid fa-sliders" aria-hidden="true"></i><span>{{ __('Display preferences') }}</span></a></li>
                    <li><a href="{{ route('security.index') }}"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>{{ __('Account security') }}</span></a></li>
                </ul>
            </nav>

            <div class="hsp-shell-language">
                <p>{{ __('Language') }}</p>
                <x-language-switcher compact />
            </div>

            <form method="POST" action="{{ route('logout') }}" class="hsp-shell-logout" data-confirm-submit="{{ __('Are you sure you want to sign out?') }}">
                @csrf
                <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>{{ __('Logout') }}</span></button>
            </form>
        </section>
    </div>
</header>

<nav class="hsp-shell-bottom-nav" aria-label="{{ __('Mobile learner navigation') }}">
    <ul>
        @foreach($learnerDestinations as $destination)
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
