@props(['points', 'label', 'tone' => 'blue', 'field' => 'count', 'summary' => null])

{{-- A 30-day trend without axes: the line, a soft fill under it and a dot on
     the newest day. The table is the accessible twin of the picture. --}}
@php
    $values = array_map(static fn (array $point): float => (float) ($point[$field] ?? 0), array_values($points));
    $total = array_sum($values);
    $max = $values === [] ? 0.0 : max($values);
    $mirror = app()->getLocale() === 'ar';
    $width = 120;
    $height = 32;
    $geometry = $values === [] ? null : \App\Support\Charts\Geometry::line($values, $max > 0 ? $max : 1.0, $width, $height, $mirror);
    $newest = $geometry ? $geometry['points'][array_key_last($geometry['points'])] : null;
    $color = \App\Support\Charts\Palette::css($tone);
    $text = $summary ?? number_format($total);
@endphp

<figure {{ $attributes->class('relative m-0') }}>
    <div role="img" aria-label="{{ $label }}, {{ __('admin.home.trends.window') }}: {{ $text }}" class="relative">
        <svg viewBox="0 0 {{ $width }} {{ $height + 4 }}" preserveAspectRatio="none" class="block h-10 w-full overflow-visible" aria-hidden="true" focusable="false">
            @if ($geometry === null || $max <= 0)
                <line x1="0" y1="{{ $height + 2 }}" x2="{{ $width }}" y2="{{ $height + 2 }}" stroke="var(--viz-muted)" stroke-width="2" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
            @else
                <g transform="translate(0 2)">
                    <path d="{{ $geometry['area'] }}" fill="{{ $color }}" opacity="0.12" />
                    <path d="{{ $geometry['line'] }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                </g>
            @endif
        </svg>
        @if ($newest && $max > 0)
            <span aria-hidden="true" class="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white"
                  style="left: {{ round($newest['x'] / $width * 100, 2) }}%; top: {{ round(($newest['y'] + 2) / ($height + 4) * 100, 2) }}%; background: {{ $color }}"></span>
        @endif
    </div>
    <figcaption class="mt-1 text-end text-[10px] leading-none text-ink-500" aria-hidden="true">{{ __('admin.home.trends.window_short') }}</figcaption>
    <div class="sr-only"><table>
        <caption>{{ __('admin.home.trends.table_caption', ['series' => $label]) }}</caption>
        <thead><tr><th scope="col">{{ __('admin.home.trends.date') }}</th><th scope="col">{{ __('admin.home.trends.count') }}</th></tr></thead>
        <tbody>
            @foreach ($points as $point)
                <tr><th scope="row">{{ $point['date'] }}</th><td>{{ $point[$field] ?? 0 }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
</figure>
