{{-- The home dashboard's chart components and palette, fed with ledger series. --}}
<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
    <x-admin.card :title="__('admin.revenue.charts.trend')" :description="$revenue->bucketLabel()" icon="trend" class="xl:col-span-2">
        <x-admin.chart.lines money :series="$revenue->trendSeries()" :label="__('admin.revenue.charts.trend')" />
        @if ($revenue->hasLoss())
            <p class="mt-3 text-xs text-ink-500">{{ __('admin.revenue.charts.loss_note') }}</p>
        @endif
    </x-admin.card>

    <x-admin.card :title="__('admin.revenue.charts.sources')" icon="banknotes">
        <x-admin.chart.donut money :slices="$revenue->sourceSlices()" :label="__('admin.revenue.charts.sources')" :caption="$revenue->currency()" />
    </x-admin.card>
</div>
