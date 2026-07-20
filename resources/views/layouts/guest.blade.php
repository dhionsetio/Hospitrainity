@php($uiUser = auth()->user())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $uiUser?->ui_theme ?? 'system' }}" data-motion="{{ $uiUser?->ui_motion ?? 'system' }}" data-text-scale="{{ $uiUser?->ui_text_scale ?? 'default' }}" data-contrast="{{ $uiUser?->ui_high_contrast ? 'stronger' : 'default' }}" data-audio="{{ $uiUser?->ui_no_audio ? 'off' : 'on' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hospitrainity')</title>

    <!-- Compiled, self-hosted assets: Tailwind, Font Awesome, Alpine CSP, and app JS. -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body class="@yield('bodyClass', 'bg-neutral-50') flex-col">
    <a href="#hsp-page-content" class="hsp-skip-link">{{ __('Skip to main content') }}</a>
    <div id="hsp-page-content" tabindex="-1">
        @yield('content')
    </div>

    @include('partials.public-footer')

    @stack('scripts')
</body>

</html>
