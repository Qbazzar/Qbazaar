@props(['href'])

<a href="{{ $href }}" {{ $attributes->class('inline-flex items-center gap-1.5 rounded-lg text-sm font-semibold text-ink-500 transition hover:text-coral-700') }}>
    <x-admin.icon name="arrow-right" class="size-4 ltr:rotate-180" />
    {{ $slot }}
</a>
