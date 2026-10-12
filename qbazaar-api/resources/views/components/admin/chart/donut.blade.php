@props(['slices', 'label', 'caption' => null])

{{-- Share of a whole as a thin ring with a legend that carries every number,
     so colour is never the only way to read a slice. Hover or focus a legend
     row (or a slice) to see it in the centre.
     slices: list of ['label', 'value', 'tone']. --}}
@php
    $slices = array_values($slices);
    $values = array_map(static fn (array $slice): int|float => $slice['value'], $slices);
    $total = array_sum($values);
    $radius = 42;
    $circumference = 2 * M_PI * $radius;
    $arcs = \App\Support\Charts\Geometry::donut($values, $radius, 2.0);
    $percent = static function (int|float $v) use ($total): string {
        $share = $total > 0 ? $v / $total * 100 : 0;

        return number_format($share, $share > 0 && $share < 10 ? 1 : 0) . '%';
    };
    $summary = implode('. ', array_map(static fn (array $s): string => $s['label'] . ': ' . number_format($s['value']), $slices));
    $mirror = app()->getLocale() === 'ar';
@endphp

<figure {{ $attributes->class('m-0 flex flex-wrap items-center gap-x-6 gap-y-4') }} data-viz-donut>
    <div class="relative size-36 shrink-0" role="img" aria-label="{{ $label }}. {{ $total > 0 ? $summary : __('admin.home.trends.none') }}">
        <svg viewBox="0 0 100 100" class="size-full {{ $mirror ? '-scale-x-100' : '' }}" aria-hidden="true" focusable="false">
            <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="var(--viz-track, #f1f2f5)" stroke-width="14" />
            @foreach ($arcs as $arc)
                <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke="{{ \App\Support\Charts\Palette::css($slices[$arc['index']]['tone']) }}" stroke-width="14"
                        stroke-dasharray="{{ $arc['length'] }} {{ round($circumference - $arc['length'], 3) }}" stroke-dashoffset="{{ -$arc['offset'] }}"
                        transform="rotate(-90 50 50)" data-slice="{{ $arc['index'] }}" />
            @endforeach
        </svg>
        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center" aria-hidden="true">
            <span data-center-value class="text-2xl leading-none font-extrabold" dir="ltr">{{ number_format($total) }}</span>
            <span data-center-label class="mt-1 max-w-20 truncate text-[11px] text-ink-500">{{ $caption }}</span>
        </div>
    </div>

    <ul class="grid min-w-40 flex-1 gap-1.5 text-sm">
        @foreach ($slices as $index => $slice)
            <li tabindex="0" data-legend="{{ $index }}" data-label="{{ $slice['label'] }}" data-value="{{ number_format($slice['value']) }}"
                class="flex items-center gap-2 rounded-md px-1.5 py-0.5 outline-hidden hover:bg-cream-50 focus-visible:outline-2 focus-visible:outline-coral-600">
                <span aria-hidden="true" class="size-2.5 shrink-0 rounded-sm" style="background: {{ \App\Support\Charts\Palette::css($slice['tone']) }}"></span>
                <span class="min-w-0 flex-1 truncate text-ink-700">{{ $slice['label'] }}</span>
                <span dir="ltr" class="font-bold">{{ number_format($slice['value']) }}</span>
                <span dir="ltr" class="w-10 text-end text-xs text-ink-500">{{ $percent($slice['value']) }}</span>
            </li>
        @endforeach
    </ul>

    <div class="sr-only"><table>
        <caption>{{ $label }}</caption>
        <thead><tr><th scope="col">{{ __('admin.home.status') }}</th><th scope="col">{{ __('admin.home.trends.count') }}</th><th scope="col">%</th></tr></thead>
        <tbody>
            @foreach ($slices as $slice)
                <tr><th scope="row">{{ $slice['label'] }}</th><td>{{ number_format($slice['value']) }}</td><td>{{ $percent($slice['value']) }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
</figure>

@once
    <script>
        (function () {
            function init(root) {
                var value = root.querySelector('[data-center-value]');
                var name = root.querySelector('[data-center-label]');
                var rest = { value: value.textContent, label: name.textContent };
                var arcs = root.querySelectorAll('[data-slice]');

                function focusSlice(index) {
                    var row = root.querySelector('[data-legend="' + index + '"]');
                    value.textContent = row.getAttribute('data-value');
                    name.textContent = row.getAttribute('data-label');
                    arcs.forEach(function (arc) { arc.style.opacity = arc.getAttribute('data-slice') === String(index) ? '1' : '0.35'; });
                }

                function reset() {
                    value.textContent = rest.value;
                    name.textContent = rest.label;
                    arcs.forEach(function (arc) { arc.style.opacity = ''; });
                }

                root.querySelectorAll('[data-legend]').forEach(function (row) {
                    var index = row.getAttribute('data-legend');
                    ['mouseenter', 'focus'].forEach(function (type) { row.addEventListener(type, function () { focusSlice(index); }); });
                    ['mouseleave', 'blur'].forEach(function (type) { row.addEventListener(type, reset); });
                });
                arcs.forEach(function (arc) {
                    arc.addEventListener('mouseenter', function () { focusSlice(arc.getAttribute('data-slice')); });
                    arc.addEventListener('mouseleave', reset);
                });
            }

            document.querySelectorAll('[data-viz-donut]').forEach(init);
        })();
    </script>
@endonce
