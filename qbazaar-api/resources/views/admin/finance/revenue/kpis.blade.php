{{-- Headline figures, each against the previous period of the same length. --}}
<section aria-label="{{ __('admin.revenue.kpis.title') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($revenue->kpis() as $kpi)
        <x-admin.dashboard.kpi :label="$kpi['label']" :value="$kpi['value']" :icon="$kpi['icon']" :change="$kpi['change']" :caption="$kpi['caption']" />
    @endforeach
</section>
