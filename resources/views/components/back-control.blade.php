@props([
    'href',
    'label',
])

<a href="{{ $href }}" {{ $attributes->class(['hsp-back-control']) }} aria-label="{{ $label }}">
    <span class="hsp-back-control__icon" aria-hidden="true">
        <i class="fa-solid fa-arrow-left"></i>
    </span>
    <span class="hsp-back-control__label">{{ $label }}</span>
</a>
