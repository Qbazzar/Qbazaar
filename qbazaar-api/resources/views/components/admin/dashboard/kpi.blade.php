@props(['label', 'value', 'icon' => null, 'href' => null, 'change' => false, 'caption' => null, 'points' => null, 'field' => 'count', 'tone' => 'blue', 'summary' => null, 'meter' => null])

{{-- One headline figure: the number, how it moved against the previous period,
     and the last 30 days as a sparkline (or a share bar for stock figures). --}}
<div {{ $attributes->class(['group relative flex h-full flex-col rounded-2xl border border-ink-200 bg-cream-100 p-4 transition', 'hover:border-coral-600 hover:shadow-sm focus-within:border-coral-600' => $href]) }}>
    <div class="flex items-center justify-between gap-2">
        @if ($href)
            <a href="{{ $href }}" class="text-sm font-medium text-ink-700 outline-hidden after:absolute after:inset-0 after:rounded-2xl focus-visible:after:outline-2 focus-visible:after:outline-coral-600">{{ $label }}</a>
        @else
            <div class="text-sm font-medium text-ink-700">{{ $label }}</div>
        @endif
        @if ($icon)
            <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-cream-200 text-ink-500 group-hover:bg-coral-soft group-hover:text-coral-700">
                <x-admin.icon :name="$icon" class="size-4" />
            </span>
        @endif
    </div>

    <div dir="ltr" class="mt-2 text-start text-3xl leading-tight font-extrabold">{{ $value }}</div>

    <div class="mt-1 flex min-h-6 flex-wrap items-center gap-x-2 gap-y-1">
        @if ($change !== false)
            @if ($change === null)
                <span class="text-xs font-medium text-ink-500">{{ __('admin.home.no_change') }}</span>
            @else
                @php($tone_class = $change > 0 ? 'bg-emerald-50 text-emerald-700' : ($change < 0 ? 'bg-red-50 text-red-700' : 'bg-cream-200 text-ink-700'))
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $tone_class }}">
                    <span aria-hidden="true">{{ $change > 0 ? '▲' : ($change < 0 ? '▼' : '–') }}</span>
                    <span dir="ltr">{{ $change > 0 ? '+' : '' }}{{ $change }}%</span>
                    <span class="sr-only">{{ __('admin.home.vs_previous') }}</span>
                </span>
            @endif
        @endif
        @if ($caption)
            <span class="text-xs text-ink-500">{{ $caption }}</span>
        @endif
    </div>

    @if ($points !== null || $meter !== null)
        <div class="mt-auto pt-3">
            @if ($points !== null)
                <x-admin.chart.sparkline :points="$points" :label="$label" :field="$field" :tone="$tone" :summary="$summary" />
            @else
                <x-admin.chart.meter :value="$meter['value']" :total="$meter['total']" :label="$label" :tone="$tone" :caption="$meter['caption'] ?? null" />
            @endif
        </div>
    @endif
</div>
