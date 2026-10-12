{{-- Headline figures with their 30-day sparkline, then the stock figures as share bars.
     The figure lists are built in dashboard.blade.php. --}}
@if ($kpis !== [])
    <section aria-label="{{ __('admin.home.sections.kpis') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]">
        @foreach ($kpis as $def)
            @php
                $tile = $tiles[$def['tile']];
                $delta = isset($def['delta']) ? $tiles[$def['delta']] : $tile;
                $points = isset($def['series']) ? ($series[$def['series']] ?? null) : null;
                $field = $def['field'] ?? 'count';
                $change = array_key_exists('change', $delta) ? $delta['change'] : false;
            @endphp
            <x-admin.dashboard.kpi
                :label="__('admin.home.tiles.' . $def['tile'])"
                :value="$showMoney($tile)"
                :icon="$def['icon']"
                :href="$tile['href'] ?? null"
                :change="$change"
                :caption="isset($def['delta']) ? __('admin.home.kpi_new', ['label' => __('admin.home.tiles.' . $def['delta']), 'count' => number_format($delta['value'])]) : null"
                :points="$points"
                :field="$field"
                :tone="$def['tone']"
                :summary="$points !== null && $field !== 'count' ? \App\Support\Money::format(\App\Support\Money::round((string) array_sum(array_column($points, $field)))) : null" />
        @endforeach
    </section>
@endif

@if ($stock !== [])
    <section aria-label="{{ __('admin.home.sections.stock') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]">
        @foreach ($stock as $def)
            @php($tile = $tiles[$def['tile']])
            <x-admin.dashboard.kpi
                :label="__('admin.home.tiles.' . $def['tile'])"
                :value="$showMoney($tile)"
                :icon="$def['icon']"
                :href="$tile['href'] ?? null"
                :tone="$def['tone']"
                :meter="['value' => $tile['value'], 'total' => $totals[$def['of']], 'caption' => __('admin.home.share.' . ($def['of'] === 'percent' ? 'verified' : $def['of']))]" />
        @endforeach
    </section>
@endif
