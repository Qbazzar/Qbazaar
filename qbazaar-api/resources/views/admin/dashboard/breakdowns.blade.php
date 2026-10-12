{{-- Top 10 categories and places by active ads, as horizontal bars. --}}
@if ($stats['breakdowns'] !== [])
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach (['categories' => 'folder', 'locations' => 'map-pin'] as $kind => $icon)
            @php($rows = array_map(fn (array $row): array => ['label' => $localized($row['name']), 'value' => $row['count']], $stats['breakdowns'][$kind]))
            <x-admin.card :title="__('admin.home.breakdowns.' . $kind)" :icon="$icon">
                <x-admin.chart.bars :rows="$rows" tone="coral" :label="__('admin.home.breakdowns.' . $kind)" :unit="__('admin.home.breakdowns.ads')" />
            </x-admin.card>
        @endforeach
    </div>
    <p class="text-xs text-ink-500">{{ __('admin.home.breakdowns.search_terms') }}</p>
@endif
