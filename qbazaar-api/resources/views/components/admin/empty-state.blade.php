@props(['icon' => 'inbox', 'message'])

<div {{ $attributes->class('px-4 py-16 text-center') }}>
    <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-500">
        <x-admin.icon :name="$icon" class="size-6" />
    </span>
    <p class="text-sm font-semibold text-ink-500">{{ $message }}</p>
    {{ $slot }}
</div>
