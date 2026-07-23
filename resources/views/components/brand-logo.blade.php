@props(['class' => 'h-8 w-auto'])

<div {{ $attributes->merge(['class' => 'inline-flex items-center']) }}>
    <picture>
        <source srcset="{{ asset('images/brand/logo-dark.svg') }}" media="(prefers-color-scheme: dark)">
        <img src="{{ asset('images/brand/logo-light.svg') }}" alt="{{ __('Hospitrainity') }}" class="dark:hidden {{ $class }}">
    </picture>
    <img src="{{ asset('images/brand/logo-dark.svg') }}" alt="{{ __('Hospitrainity') }}" class="hidden dark:block {{ $class }}">
</div>
