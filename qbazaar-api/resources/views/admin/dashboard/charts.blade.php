{{-- Main row: the 30-day trends (and money for finance) at two thirds, the status donuts at one third. --}}
@php
    $trendDefs = [
        ['key' => 'users', 'tone' => 'blue'],
        ['key' => 'ads', 'tone' => 'coral'],
        ['key' => 'orders', 'tone' => 'violet'],
    ];
    $trendSeries = [];
    foreach ($trendDefs as $def) {
        if (isset($series[$def['key']])) {
            $trendSeries[] = ['label' => __('admin.home.trends.' . $def['key']), 'tone' => $def['tone'], 'field' => 'count', 'points' => $series[$def['key']]];
        }
    }
    $revenue = $series['revenue'] ?? null;

    $adTones = ['active' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'expired' => 'slate', 'sold' => 'violet', 'blocked' => 'coral', 'draft' => 'muted'];
    $orderTones = ['created' => 'info', 'awaiting_handover' => 'warning', 'completed' => 'success', 'cancelled' => 'muted', 'disputed' => 'danger'];
    $slices = [];
    foreach (($distributions['ads'] ?? []) as $status => $count) {
        if ($count > 0) {
            $slices['ads'][] = ['label' => \App\Enums\AdStatus::from($status)->label()[$locale] ?? $status, 'value' => $count, 'tone' => $adTones[$status] ?? 'slate'];
        }
    }
    foreach (($distributions['orders'] ?? []) as $status => $count) {
        if ($count > 0) {
            $slices['orders'][] = ['label' => __('admin.home.order_status.' . $status), 'value' => $count, 'tone' => $orderTones[$status] ?? 'slate'];
        }
    }
    $donuts = array_filter(['ads' => isset($distributions['ads']), 'orders' => isset($distributions['orders'])]);
@endphp

@if ($trendSeries !== [] || $donuts !== [])
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-12">
        @if ($trendSeries !== [])
            <div @class(['flex flex-col gap-4 md:col-span-2', $donuts !== [] ? 'xl:col-span-8' : 'xl:col-span-12'])>
                <x-admin.card :title="__('admin.home.trends.title')" icon="trend" class="flex-1">
                    <x-admin.chart.lines :series="$trendSeries" :label="__('admin.home.trends.title')" height="h-64" />
                </x-admin.card>

                @if ($revenue !== null)
                    <x-admin.card :title="__('admin.home.trends.revenue')" icon="banknotes">
                        <div class="grid grid-cols-1 gap-x-8 gap-y-6 md:grid-cols-2">
                            <x-admin.chart.lines money height="h-40" :label="__('admin.home.trends.gmv')"
                                :series="[['label' => __('admin.home.trends.gmv'), 'tone' => 'violet', 'field' => 'gmv', 'points' => $revenue]]" />
                            <x-admin.chart.lines money height="h-40" :label="__('admin.home.trends.commission')"
                                :series="[['label' => __('admin.home.trends.commission'), 'tone' => 'aqua', 'field' => 'commission', 'points' => $revenue]]" />
                        </div>
                    </x-admin.card>
                @endif
            </div>
        @endif

        @if ($donuts !== [])
            <div @class(['grid grid-cols-1 gap-4 md:col-span-2', $trendSeries !== [] ? 'xl:col-span-4 xl:grid-cols-1' : 'xl:col-span-12 xl:grid-cols-2'])>
                @foreach (array_keys($donuts) as $kind)
                    <x-admin.card :title="__('admin.home.distribution.' . $kind)" :icon="$kind === 'ads' ? 'tag' : 'banknotes'" :description="$kind === 'orders' ? $period->label() : null">
                        <x-admin.chart.donut :slices="$slices[$kind] ?? []" :label="__('admin.home.distribution.' . $kind)" :caption="__('admin.home.distribution.' . $kind . '_caption')" />
                    </x-admin.card>
                @endforeach
            </div>
        @endif
    </div>
@endif
