<footer class="border-t border-neutral-200 bg-white px-4 py-6 text-sm text-neutral-700">
    <nav aria-label="{{ __('Public trust and support') }}" class="mx-auto flex max-w-6xl flex-wrap justify-center gap-x-5 gap-y-3">
        <a href="{{ route('policies.show', ['type' => 'privacy']) }}" class="underline hover:text-indigo-700">{{ __('Privacy') }}</a>
        <a href="{{ route('policies.show', ['type' => 'terms']) }}" class="underline hover:text-indigo-700">{{ __('Terms') }}</a>
        <a href="{{ route('policies.show', ['type' => 'accessibility']) }}" class="underline hover:text-indigo-700">{{ __('Accessibility') }}</a>
        <a href="{{ route('policies.show', ['type' => 'acceptable-use']) }}" class="underline hover:text-indigo-700">{{ __('Acceptable use') }}</a>
        <a href="{{ route('help.index') }}" class="underline hover:text-indigo-700">{{ __('Help') }}</a>
        <a href="{{ route('glossary.index') }}" class="underline hover:text-indigo-700">{{ __('Glossary') }}</a>
        <a href="{{ route('about') }}" class="underline hover:text-indigo-700">{{ __('About') }}</a>
        @auth<a href="{{ route('privacy-requests.index') }}" class="font-semibold underline hover:text-indigo-700">{{ __('My privacy requests') }}</a>@endauth
    </nav>
    <p class="mx-auto mt-3 max-w-4xl text-center text-xs text-neutral-600">{{ __('Non-production thesis prototype. Public documents describe current verified behavior and do not claim independent legal or accessibility conformance review.') }}</p>
</footer>
