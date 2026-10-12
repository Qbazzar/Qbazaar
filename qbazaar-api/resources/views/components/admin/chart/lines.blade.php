@props(['series', 'label', 'money' => false, 'height' => 'h-56', 'fill' => false])

{{-- Multi-series daily line chart on ONE zero-based axis (all series share a unit).
     series: list of ['label', 'tone', 'field', 'points' => list<['date', <field>]>].
     Hover, touch or arrow keys read one day across all series; without JS the
     legend shows totals and the table holds every value. --}}
@php
    $mirror = app()->getLocale() === 'ar';
    // In fill mode the chart takes the height its card is given (min 16rem) instead of a fixed one.
    $height = $fill ? 'h-full' : $height;
    $series = array_values($series);
    $dates = array_column($series[0]['points'] ?? [], 'date');
    $column = static fn (array $s): array => array_map(static fn (array $p): float => (float) ($p[$s['field'] ?? 'count'] ?? 0), array_values($s['points']));
    $columns = array_map($column, $series);
    $peak = $columns === [] ? 0.0 : max(array_map(static fn (array $c): float => $c === [] ? 0.0 : max($c), $columns));
    $axis = \App\Support\Charts\Geometry::axis($peak, 4, ! $money);
    $format = static fn (float $v): string => $money ? \App\Support\Money::format(\App\Support\Money::round((string) $v)) : number_format($v);
    $decimals = $axis['step'] < 1 ? 2 : (fmod($axis['step'], 1.0) > 0 ? 1 : 0);
    $axisFormat = static fn (float $v): string => match (true) {
        $v >= 1_000_000 => rtrim(rtrim(number_format($v / 1_000_000, 1), '0'), '.') . 'M',
        $v >= 1000 => rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'K',
        default => number_format($v, $decimals),
    };
    $day = static fn (string $date): string => \Illuminate\Support\Carbon::parse($date)->translatedFormat('j M');
    $drawn = [];
    foreach ($series as $index => $s) {
        $drawn[$index] = \App\Support\Charts\Geometry::line($columns[$index], $axis['top'], 100, 100, $mirror);
    }
    $xs = array_map(static fn (array $p): float => round($p['x'], 2), $drawn[0]['points'] ?? []);
    $data = [
        'mirror' => $mirror,
        'dates' => array_map($day, $dates),
        'x' => $xs,
        'series' => array_map(static fn (array $s, int $i): array => [
            'label' => $s['label'],
            'color' => \App\Support\Charts\Palette::css($s['tone']),
            'y' => array_map(static fn (array $p): float => round($p['y'], 2), $drawn[$i]['points']),
            'text' => array_map($format, $columns[$i]),
        ], $series, array_keys($series)),
    ];
    $ticks = \App\Support\Charts\Geometry::ticks(count($dates), 5);
    $steps = max(1, (int) round($axis['top'] / $axis['step']));
    $hasData = $peak > 0;
    $summary = implode('. ', array_map(static fn (array $s, int $i): string => $s['label'] . ': ' . $format(array_sum($columns[$i])), $series, array_keys($series)));
@endphp

<figure {{ $attributes->class(['m-0', 'flex min-h-0 flex-1 flex-col' => $fill]) }} data-viz-lines="{{ json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}">
    <figcaption class="mb-3 flex flex-wrap gap-x-5 gap-y-1">
        @foreach ($series as $index => $s)
            <span class="inline-flex items-center gap-2 text-xs text-ink-700">
                <span aria-hidden="true" class="inline-block h-1 w-4 rounded-full" style="background: {{ \App\Support\Charts\Palette::css($s['tone']) }}"></span>
                <span class="font-semibold">{{ $s['label'] }}</span>
                <span class="text-ink-500">{{ __('admin.home.trends.total', ['total' => $format(array_sum($columns[$index]))]) }}</span>
            </span>
        @endforeach
    </figcaption>

    @if (! $hasData)
        <p class="flex {{ $height }} items-center justify-center rounded-xl bg-cream-50 text-sm text-ink-500">{{ __('admin.home.trends.none') }}</p>
    @else
        <div @class(['grid grid-cols-[auto_1fr] gap-x-2', 'flex-1 grid-rows-[minmax(16rem,1fr)_auto]' => $fill])>
            <div class="relative {{ $height }} min-w-8 text-[11px] text-ink-500" aria-hidden="true">
                @for ($step = 0; $step <= $steps; $step++)
                    <span dir="ltr" class="absolute end-0 translate-y-1/2 leading-none" style="bottom: {{ round($step / $steps * 100, 2) }}%">{{ $axisFormat($step * $axis['step']) }}</span>
                @endfor
            </div>
            <div class="relative {{ $height }} touch-pan-y rounded-sm outline-hidden focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-coral-600"
                 data-plot tabindex="0" role="group" aria-label="{{ __('admin.home.trends.keyboard_hint') }}">
                @for ($step = 0; $step <= $steps; $step++)
                    <span aria-hidden="true" class="absolute inset-x-0 border-t {{ $step === 0 ? 'border-ink-300' : 'border-dashed border-ink-200' }}" style="bottom: {{ round($step / $steps * 100, 2) }}%"></span>
                @endfor
                <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 size-full overflow-visible" role="img" aria-label="{{ $label }}. {{ $summary }}">
                    <title>{{ $label }}</title>
                    @foreach ($series as $index => $s)
                        @if (count($series) === 1)
                            <path d="{{ $drawn[$index]['area'] }}" fill="{{ \App\Support\Charts\Palette::css($s['tone']) }}" opacity="0.12" />
                        @endif
                        <path d="{{ $drawn[$index]['line'] }}" fill="none" stroke="{{ \App\Support\Charts\Palette::css($s['tone']) }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                    @endforeach
                </svg>
                <span data-line hidden aria-hidden="true" class="pointer-events-none absolute inset-y-0 w-px bg-ink-500/50"></span>
                @foreach ($series as $s)
                    <span data-dot hidden aria-hidden="true" class="pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white" style="background: {{ \App\Support\Charts\Palette::css($s['tone']) }}"></span>
                @endforeach
                <div data-tip hidden aria-hidden="true" class="pointer-events-none absolute top-0 z-10 min-w-32 rounded-lg border border-ink-200 bg-white px-3 py-2 text-xs shadow-md"></div>
                <span data-live class="sr-only" aria-live="polite"></span>
            </div>
            <div></div>
            <div class="mt-2 flex justify-between text-[11px] text-ink-500" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <span>{{ $day($dates[$tick]) }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="sr-only"><table>
        <caption>{{ $label }}</caption>
        <thead>
            <tr>
                <th scope="col">{{ __('admin.home.trends.date') }}</th>
                @foreach ($series as $s)<th scope="col">{{ $s['label'] }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($dates as $i => $date)
                <tr>
                    <th scope="row">{{ $date }}</th>
                    @foreach ($series as $index => $s)<td>{{ $format($columns[$index][$i]) }}</td>@endforeach
                </tr>
            @endforeach
        </tbody>
    </table></div>
</figure>

@once
    <script>
        (function () {
            function el(tag, cls, text) {
                var node = document.createElement(tag);
                if (cls) node.className = cls;
                if (text !== undefined) node.textContent = text;
                return node;
            }

            function init(root) {
                var data = JSON.parse(root.getAttribute('data-viz-lines'));
                var plot = root.querySelector('[data-plot]');
                if (!plot || !data.dates.length) return;
                var line = plot.querySelector('[data-line]');
                var tip = plot.querySelector('[data-tip]');
                var live = plot.querySelector('[data-live]');
                var dots = Array.prototype.slice.call(plot.querySelectorAll('[data-dot]'));
                var current = -1;

                function show(index) {
                    current = Math.max(0, Math.min(data.dates.length - 1, index));
                    var x = data.x[current];
                    line.hidden = false;
                    line.style.left = x + '%';
                    tip.textContent = '';
                    tip.appendChild(el('div', 'mb-1 font-semibold text-ink-900', data.dates[current]));
                    var spoken = [data.dates[current]];
                    data.series.forEach(function (s, k) {
                        dots[k].hidden = false;
                        dots[k].style.left = x + '%';
                        dots[k].style.top = s.y[current] + '%';
                        var row = el('div', 'flex items-center justify-between gap-4 text-ink-700');
                        var name = el('span', 'inline-flex items-center gap-1.5');
                        var swatch = el('span', 'inline-block size-2 rounded-full');
                        swatch.style.background = s.color;
                        name.appendChild(swatch);
                        name.appendChild(document.createTextNode(s.label));
                        row.appendChild(name);
                        row.appendChild(el('span', 'font-bold text-ink-900', s.text[current]));
                        tip.appendChild(row);
                        spoken.push(s.label + ' ' + s.text[current]);
                    });
                    tip.hidden = false;
                    tip.style.left = x + '%';
                    tip.style.transform = x > 55 ? 'translateX(calc(-100% - 12px))' : 'translateX(12px)';
                    live.textContent = spoken.join(', ');
                }

                function hide() {
                    current = -1;
                    line.hidden = true;
                    tip.hidden = true;
                    dots.forEach(function (dot) { dot.hidden = true; });
                    live.textContent = '';
                }

                function nearest(clientX) {
                    var box = plot.getBoundingClientRect();
                    var pct = (clientX - box.left) / box.width * 100;
                    var best = 0;
                    data.x.forEach(function (x, i) { if (Math.abs(x - pct) < Math.abs(data.x[best] - pct)) best = i; });
                    return best;
                }

                plot.addEventListener('pointermove', function (e) { show(nearest(e.clientX)); });
                plot.addEventListener('pointerdown', function (e) { show(nearest(e.clientX)); });
                plot.addEventListener('pointerleave', function () { if (document.activeElement !== plot) hide(); });
                plot.addEventListener('blur', hide);
                plot.addEventListener('focus', function () { if (current < 0) show(data.dates.length - 1); });
                plot.addEventListener('keydown', function (e) {
                    // Arrow keys move visually; the series is mirrored in RTL, so the index runs the other way.
                    var visual = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
                    var next = current;
                    if (visual) next = current + visual * (data.mirror ? -1 : 1);
                    else if (e.key === 'Home') next = 0;
                    else if (e.key === 'End') next = data.dates.length - 1;
                    else if (e.key === 'Escape') return hide();
                    else return;
                    e.preventDefault();
                    show(next);
                });
            }

            // The script sits next to the first chart, so later figures do not exist yet.
            function run() { document.querySelectorAll('[data-viz-lines]').forEach(init); }
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
            else run();
        })();
    </script>
@endonce
