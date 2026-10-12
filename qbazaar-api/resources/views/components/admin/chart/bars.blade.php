@props(['rows', 'label', 'tone' => 'coral', 'unit' => null])

{{-- Horizontal bars on a zero-based scale. The category sits at the start, the
     value at the end, and every value is printed, so nothing depends on hover.
     rows: list of ['label', 'value']. --}}
@php
    $rows = array_values($rows);
    $max = $rows === [] ? 0 : max(array_column($rows, 'value'));
    $total = array_sum(array_column($rows, 'value'));
    $color = \App\Support\Charts\Palette::css($tone);
    $mirror = app()->getLocale() === 'ar';
    $summary = implode('. ', array_map(static fn (array $row): string => $row['label'] . ': ' . number_format($row['value']), array_slice($rows, 0, 3)));
@endphp

<figure {{ $attributes->class('m-0') }}>
    @if ($rows === [] || $max <= 0)
        <p class="py-10 text-center text-sm text-ink-500">{{ __('admin.home.empty') }}</p>
    @else
        <ol role="img" aria-label="{{ $label }}. {{ $summary }}" class="grid items-center gap-x-3 gap-y-2.5" style="grid-template-columns: minmax(6rem, 36%) 1fr auto">
            @foreach ($rows as $row)
                @php($width = $row['value'] > 0 ? max(1.5, $row['value'] / $max * 100) : 0)
                <li class="contents">
                    <span class="truncate text-sm font-medium text-ink-700" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <svg viewBox="0 0 100 10" preserveAspectRatio="none" class="h-2.5 w-full" aria-hidden="true" focusable="false">
                        <rect x="0" y="0" width="100" height="10" rx="2" fill="var(--viz-track, #f1f2f5)" />
                        @if ($width > 0)
                            <rect x="{{ $mirror ? round(100 - $width, 2) : 0 }}" y="0" width="{{ round($width, 2) }}" height="10" rx="2" fill="{{ $color }}" />
                        @endif
                    </svg>
                    <span dir="ltr" class="text-end text-sm font-bold text-ink-900">{{ number_format($row['value']) }}</span>
                </li>
            @endforeach
        </ol>
        <p class="mt-4 text-xs text-ink-500">{{ __('admin.home.trends.total', ['total' => number_format($total)]) }}@if ($unit) · {{ $unit }}@endif</p>
    @endif

    <div class="sr-only"><table>
        <caption>{{ $label }}</caption>
        <thead><tr><th scope="col">{{ __('admin.home.breakdowns.name') }}</th><th scope="col">{{ $unit ?? __('admin.home.trends.count') }}</th></tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['value'] }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
</figure>
