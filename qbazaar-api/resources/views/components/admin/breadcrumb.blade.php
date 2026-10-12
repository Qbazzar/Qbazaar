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
                {{-- A pseudo-element separator: screen readers skip it and it follows the text direction. --}}
                <li class="min-w-0 not-first:before:mx-1.5 not-first:before:text-ink-300 not-first:before:content-['‹']"
                    @if ($loop->last) aria-current="page" @endif>
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
