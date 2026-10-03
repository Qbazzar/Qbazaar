@props(['label', 'value', 'icon' => null, 'href' => null])

@php($tag = $href ? 'a' : 'div')

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['group block rounded-2xl border border-ink-200 bg-cream-100 p-5', 'transition hover:border-coral-600 hover:shadow-sm' => $href]) }}>
    <div class="flex items-center justify-between gap-2">
        <div class="text-sm font-medium text-ink-500">{{ $label }}</div>
        @if ($icon)
            <span @class([
                'flex size-8 shrink-0 items-center justify-center rounded-lg bg-cream-200 text-ink-500',
                'group-hover:bg-coral-soft group-hover:text-coral-700' => $href,
            ])>
                <x-admin.icon :name="$icon" class="size-[18px]" />
            </span>
        @endif
    </div>
    <div class="mt-2 text-3xl font-extrabold">{{ $value }}</div>
</{{ $tag }}>
