@php
    $uiUser = auth()->user();
    $uiUseAppShell = $uiUser !== null && $uiUser->hasVerifiedEmail();
    $uiUseLearnerShell = $uiUseAppShell && app(\App\Services\WorkContext::class)->current(request(), $uiUser) === \App\Enums\WorkContextRole::Learner;
    $isDarkMode = request()->cookie('hospitrainity_theme') === 'dark' || ($uiUser?->ui_theme === 'dark');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isDarkMode ? 'dark' : '' }}" data-shell="{{ $uiUseLearnerShell ? 'learner' : ($uiUseAppShell ? 'app' : 'default') }}" data-confirm-fallback="{{ __('Are you sure you want to continue?') }}" data-theme="{{ $isDarkMode ? 'dark' : ($uiUser?->ui_theme ?? 'system') }}" data-motion="{{ $uiUser?->ui_motion ?? 'system' }}" data-text-scale="{{ $uiUser?->ui_text_scale ?? 'default' }}" data-contrast="{{ $uiUser?->ui_high_contrast ? 'stronger' : 'default' }}" data-audio="{{ $uiUser?->ui_no_audio ? 'off' : 'on' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hospitrainity')</title>

    <!-- Compiled, self-hosted assets: Tailwind, Font Awesome, Alpine CSP, and app JS. -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body class="@yield('bodyClass', 'bg-neutral-100'){{ $uiUseAppShell ? ' hsp-has-learner-shell' : '' }}">
    <a href="#hsp-page-content" class="hsp-skip-link">{{ __('Skip to main content') }}</a>
    @if(auth()->check() && request()->hasSession() && session(\App\Services\WorkContext::SESSION_PREVIEW_KEY) === true)
        <div class="border-b border-amber-400 bg-amber-100 px-4 py-3 text-center font-bold text-amber-950" role="status">
            {{ __('Preview mode is active. Actions still use your System Admin authority and are audited.') }}
            <a href="{{ route('work-context.index') }}" class="ml-2 underline">{{ __('Exit or switch context') }}</a>
        </div>
    @endif
    @if($uiUseLearnerShell)
        @include('partials.learner-shell')
    @elseif($uiUseAppShell)
        @include('partials.app-shell')
    @endif
    <div id="hsp-page-content" tabindex="-1">
        @yield('content')
    </div>

    @include('partials.public-footer')

    @stack('scripts')
</body>

</html>
