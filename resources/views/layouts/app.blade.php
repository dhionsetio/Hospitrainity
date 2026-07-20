<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-confirm-fallback="{{ __('Are you sure you want to continue?') }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hospitrainity')</title>

    <!-- Compiled, self-hosted assets: Tailwind, Font Awesome, Alpine CSP, and app JS. -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>

<body class="@yield('bodyClass', 'bg-neutral-100')">
    @if(auth()->check() && request()->hasSession() && session(\App\Services\WorkContext::SESSION_PREVIEW_KEY) === true)
        <div class="border-b border-amber-400 bg-amber-100 px-4 py-3 text-center font-bold text-amber-950" role="status">
            {{ __('Preview mode is active. Actions still use your System Admin authority and are audited.') }}
            <a href="{{ route('work-context.index') }}" class="ml-2 underline">{{ __('Exit or switch context') }}</a>
        </div>
    @endif
    @yield('content')

    @stack('scripts')
</body>

</html>
