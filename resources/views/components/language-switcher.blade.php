@props([
    'compact' => false,
    'menu' => false,
])

@php
    $currentLocale = app()->getLocale();
    $languages = [
        ['locale' => 'id', 'label' => __('Bahasa Indonesia'), 'short' => 'ID', 'flag' => 'id'],
        ['locale' => 'en', 'label' => __('English'), 'short' => 'EN', 'flag' => 'gb'],
    ];
@endphp

<div
    data-language-switcher
    data-current-locale="{{ $currentLocale }}"
    role="group"
    aria-label="{{ __('Language') }}"
    {{ $attributes->class($menu ? 'grid gap-1 px-2 py-2' : 'flex flex-wrap items-center gap-1') }}
>
    @foreach($languages as $language)
        @php($isCurrent = $currentLocale === $language['locale'])
        <a
            href="{{ route('locale.switch', $language['locale']) }}"
            hreflang="{{ $language['locale'] }}"
            data-locale-option="{{ $language['locale'] }}"
            @if($menu) role="menuitem" tabindex="-1" @endif
            @if($isCurrent) aria-current="true" @endif
            aria-label="{{ $language['label'] }}{{ $isCurrent ? ' — '.__('Current language') : '' }}"
            @class([
                'inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition-colors',
                'w-full justify-start' => $menu,
                'border-indigo-300 bg-indigo-50 text-indigo-950 shadow-sm' => $isCurrent,
                'border-transparent text-neutral-700 hover:border-neutral-300 hover:bg-neutral-100' => ! $isCurrent,
            ])
        >
            @if($language['flag'] === 'id')
                <svg data-flag="id" aria-hidden="true" focusable="false" viewBox="0 0 3 2" class="h-4 w-6 shrink-0 rounded-sm shadow-sm ring-1 ring-neutral-300">
                    <path fill="#e70011" d="M0 0h3v1H0z"/>
                    <path fill="#fff" d="M0 1h3v1H0z"/>
                </svg>
            @else
                <svg data-flag="gb" aria-hidden="true" focusable="false" viewBox="0 0 60 30" class="h-4 w-6 shrink-0 rounded-sm shadow-sm ring-1 ring-neutral-300">
                    <path fill="#012169" d="M0 0h60v30H0z"/>
                    <path stroke="#fff" stroke-width="6" d="m0 0 60 30M60 0 0 30"/>
                    <path stroke="#c8102e" stroke-width="2" d="m0 0 60 30M60 0 0 30"/>
                    <path stroke="#fff" stroke-width="10" d="M30 0v30M0 15h60"/>
                    <path stroke="#c8102e" stroke-width="6" d="M30 0v30M0 15h60"/>
                </svg>
            @endif
            <span>{{ $compact ? $language['short'] : $language['label'] }}</span>
            @if($isCurrent)
                <span class="ml-auto text-indigo-700" aria-hidden="true">✓</span>
            @endif
        </a>
    @endforeach
</div>
