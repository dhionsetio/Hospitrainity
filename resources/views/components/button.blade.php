@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $baseStyles = 'inline-flex items-center justify-center font-semibold rounded-lg transition-colors duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer';

    $variants = [
        'primary' => 'bg-indigo-600 text-white hover:bg-indigo-700 active:bg-indigo-800 focus:ring-indigo-500 shadow-sm border border-transparent',
        'secondary' => 'bg-neutral-100 text-neutral-800 hover:bg-neutral-200 active:bg-neutral-300 focus:ring-neutral-400 border border-neutral-300 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700 dark:border-neutral-600',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 active:bg-red-800 focus:ring-red-500 shadow-sm border border-transparent',
        'outline' => 'bg-transparent text-indigo-600 border border-indigo-600 hover:bg-indigo-50 focus:ring-indigo-500 dark:text-indigo-400 dark:border-indigo-500 dark:hover:bg-neutral-800',
        'ghost' => 'bg-transparent text-neutral-700 hover:bg-neutral-100 focus:ring-neutral-400 dark:text-neutral-300 dark:hover:bg-neutral-800',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs gap-1.5',
        'md' => 'px-4 py-2 text-sm gap-2',
        'lg' => 'px-5 py-2.5 text-base gap-2.5',
    ];

    $classes = implode(' ', [
        $baseStyles,
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
