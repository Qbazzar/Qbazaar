@props(['icon' => 'inbox', 'message', 'compact' => false])

<div {{ $attributes->class(['px-4 text-center', 'py-16' => ! $compact, 'py-8' => $compact]) }}>
    <span @class([
        'mx-auto flex items-center justify-center rounded-full bg-cream-200 text-ink-500',
        'mb-3 size-12' => ! $compact,
        'mb-2 size-10' => $compact,
    ])>
        <x-admin.icon :name="$icon" :class="$compact ? 'size-5' : 'size-6'" />
    </span>
    <p class="text-sm font-semibold text-ink-500">{{ $message }}</p>
    {{ $slot }}
</div>
