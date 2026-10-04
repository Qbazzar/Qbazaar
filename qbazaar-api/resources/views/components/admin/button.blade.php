@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'type' => 'submit',
    'block' => false,
])

@php
    $variants = [
        'primary' => 'bg-coral-600 font-bold text-white hover:bg-coral-700',
        'secondary' => 'border border-ink-200 bg-cream-100 font-semibold text-ink-700 hover:bg-cream-200',
        'danger' => 'bg-red-600 font-bold text-white hover:bg-red-700',
        'success' => 'bg-emerald-700 font-bold text-white hover:bg-emerald-800',
        'info' => 'bg-sky-700 font-bold text-white hover:bg-sky-800',
        'ghost' => 'font-semibold text-ink-500 hover:bg-cream-200 hover:text-ink-900',
        'danger-ghost' => 'font-semibold text-red-700 hover:bg-red-50',
    ];
    $sizes = [
        'sm' => 'gap-1.5 rounded-lg px-3 py-1.5 text-xs',
        'md' => 'gap-2 rounded-xl px-4 py-2.5 text-sm',
    ];
    $classes = [
        'inline-flex items-center justify-center transition disabled:cursor-not-allowed disabled:opacity-60',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
        'w-full' => $block,
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-admin.icon :name="$icon" class="size-[18px] shrink-0" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-admin.icon :name="$icon" class="size-[18px] shrink-0" />@endif
        {{ $slot }}
    </button>
@endif
