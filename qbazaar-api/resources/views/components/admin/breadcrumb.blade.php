@props(['items' => []])

@php
    // Each item is [label, url?] or ['label' => ..., 'url' => ...]; the last one is the current page.
    // Latin runs (ids like #4821, model names) are isolated so they never reorder the Arabic around them.
    $isolate = fn (mixed $label): \Illuminate\Contracts\Support\Htmlable => $label instanceof \Illuminate\Contracts\Support\Htmlable
        ? $label
        : new \Illuminate\Support\HtmlString(collect(preg_split('/([#A-Za-z0-9][#A-Za-z0-9 ._:\/-]*[A-Za-z0-9]|[A-Za-z0-9])/', (string) $label, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [])
            ->map(fn (string $part, int $index): string => $index % 2 === 1 ? '<bdi dir="ltr">' . e($part) . '</bdi>' : e($part))
            ->implode(''));
    $items = collect($items)
        ->map(fn (mixed $item): array => is_array($item)
            ? ['label' => $isolate($item['label'] ?? $item[0]), 'url' => $item['url'] ?? $item[1] ?? null]
            : ['label' => $isolate($item), 'url' => null])
        ->values();
@endphp

@if ($items->isNotEmpty())
    <nav aria-label="{{ __('admin.shell.breadcrumb') }}" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-y-0.5 text-[13px] text-ink-500">
            @foreach ($items as $item)
                {{-- The chevron points to where the trail continues: right in LTR, flipped to the left in RTL. --}}
                <li class="flex min-w-0 items-center" @if ($loop->last) aria-current="page" @endif>
                    @unless ($loop->first)
                        <svg class="mx-1.5 size-3 shrink-0 text-ink-300 rtl:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-qb-crumb-separator><path d="m9 6 6 6-6 6"/></svg>
                    @endunless
                    @if ($item['url'] && ! $loop->last)
                        <a href="{{ $item['url'] }}" class="rounded transition hover:text-coral-700">{{ $item['label'] }}</a>
                    @else
                        <span @class(['font-semibold text-ink-700' => $loop->last])>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
