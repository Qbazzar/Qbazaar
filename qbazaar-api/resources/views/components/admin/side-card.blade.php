@props(['title', 'icon' => null, 'link' => null, 'id' => null])

@php
    $id ??= 'side-' . (\Illuminate\Support\Str::slug((string) $title) ?: substr(md5((string) $title), 0, 8));
    // link: [label, url] or ['label' => ..., 'url' => ...], e.g. "view all" or "open profile".
    $link = is_array($link) ? ['label' => $link['label'] ?? $link[0], 'url' => $link['url'] ?? $link[1]] : null;
@endphp

<section aria-labelledby="{{ $id }}-title" {{ $attributes->class('rounded-2xl border border-ink-200 bg-cream-100') }}>
    <div class="flex items-center justify-between gap-3 border-b border-ink-200 px-5 py-3.5">
        <h2 id="{{ $id }}-title" class="flex min-w-0 items-center gap-2 text-[13px] font-bold text-ink-700">
            @if ($icon)<x-admin.icon :name="$icon" class="size-4 shrink-0 text-coral-600" />@endif
            {{ $title }}
        </h2>
        @isset($actions)
            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @endisset
        @if ($link)
            <a href="{{ $link['url'] }}" class="shrink-0 rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-semibold text-ink-700 transition hover:bg-coral-soft hover:text-coral-700">{{ $link['label'] }}</a>
        @endif
    </div>
    <div class="p-5 text-sm">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="border-t border-ink-200 px-5 py-3 text-[13px]">{{ $footer }}</div>
    @endisset
</section>
