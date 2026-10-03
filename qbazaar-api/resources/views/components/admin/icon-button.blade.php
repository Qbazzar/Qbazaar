@props([
    'icon',
    'label',
    'href' => null,
    'variant' => 'default',
    'type' => 'submit',
])

@php
    $classes = [
        'inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition md:size-8',
        'hover:bg-coral-soft hover:text-coral-700' => $variant !== 'danger',
        'hover:bg-red-50 hover:text-red-700' => $variant === 'danger',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-admin.icon :name="$icon" class="size-[18px]" />
    </a>
@else
    <button type="{{ $type }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-admin.icon :name="$icon" class="size-[18px]" />
    </button>
@endif
