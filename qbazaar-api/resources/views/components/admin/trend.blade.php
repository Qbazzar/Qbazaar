@props(['change' => null])

{{-- Percentage change against the previous period; null when there was nothing to compare with. --}}
@if ($change === null)
    <span class="text-xs font-medium text-ink-500">{{ __('admin.home.no_change') }}</span>
@else
    @php($tone = $change > 0 ? 'text-emerald-700' : ($change < 0 ? 'text-red-700' : 'text-ink-500'))
    <span {{ $attributes->class(['inline-flex items-center gap-1 text-xs font-semibold', $tone]) }}>
        <span aria-hidden="true">{{ $change > 0 ? '▲' : ($change < 0 ? '▼' : '–') }}</span>
        <span dir="ltr">{{ $change > 0 ? '+' : '' }}{{ $change }}%</span>
        <span class="sr-only">{{ __('admin.home.vs_previous') }}</span>
    </span>
@endif
