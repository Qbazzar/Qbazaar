<div @class(['grid grid-cols-1 gap-4', 'xl:grid-cols-2' => $revenue->showsSellers()])>
    <section aria-labelledby="revenue-categories" class="grid gap-3">
        <h2 id="revenue-categories" class="text-[15px] font-bold text-ink-900">{{ __('admin.revenue.tables.categories') }}</h2>
        <x-admin.table
            :caption="__('admin.revenue.tables.categories')"
            :columns="[__('admin.revenue.tables.category'), __('admin.revenue.tables.income'), __('admin.revenue.tables.share')]"
            :empty="$revenue->categories() === []"
            empty-icon="folder"
            :empty-message="__('admin.revenue.tables.none')"
        >
            @foreach ($revenue->categories() as $row)
                <x-admin.table.row>
                    <x-admin.table.cell primary>{{ $row['label'] }}</x-admin.table.cell>
                    <x-admin.table.cell :label="__('admin.revenue.tables.income')" class="font-bold"><span dir="ltr">{{ $row['income'] }}</span></x-admin.table.cell>
                    <x-admin.table.cell :label="__('admin.revenue.tables.share')" class="text-ink-500"><span dir="ltr">{{ $row['share'] }}</span></x-admin.table.cell>
                </x-admin.table.row>
            @endforeach
        </x-admin.table>
    </section>

    @if ($revenue->showsSellers())
        <section aria-labelledby="revenue-sellers" class="grid gap-3">
            <h2 id="revenue-sellers" class="text-[15px] font-bold text-ink-900">{{ __('admin.revenue.tables.sellers') }}</h2>
            <x-admin.table
                :caption="__('admin.revenue.tables.sellers')"
                :columns="[__('admin.revenue.tables.seller'), __('admin.revenue.tables.commission'), __('admin.revenue.tables.orders')]"
                :empty="$revenue->sellers() === []"
                empty-icon="users"
                :empty-message="__('admin.revenue.tables.none')"
            >
                @foreach ($revenue->sellers() as $row)
                    <x-admin.table.row>
                        <x-admin.table.cell primary><a class="font-semibold text-coral-700 hover:underline" href="{{ $row['url'] }}">{{ $row['name'] }}</a></x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.revenue.tables.commission')" class="font-bold"><span dir="ltr">{{ $row['commission'] }}</span></x-admin.table.cell>
                        <x-admin.table.cell :label="__('admin.revenue.tables.orders')" class="text-ink-500"><span dir="ltr">{{ $row['orders'] }}</span></x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach
            </x-admin.table>
        </section>
    @endif
</div>

<section aria-labelledby="revenue-lines" class="grid gap-3">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="revenue-lines" class="text-[15px] font-bold text-ink-900">{{ __('admin.revenue.tables.latest') }}</h2>
        <p class="text-[13px] text-ink-500">{{ __('admin.revenue.tables.latest_note', ['pages' => \App\Services\Finance\Revenue\RevenueReportService::MAX_PAGES]) }}</p>
    </div>
    <x-admin.table
        :caption="__('admin.revenue.tables.latest')"
        :columns="[__('admin.revenue.tables.when'), __('admin.revenue.tables.type'), __('admin.revenue.tables.amount'), __('admin.revenue.tables.subject'), __('admin.revenue.tables.open')]"
        :empty="$revenue->transactions() === []"
        empty-icon="inbox"
        :empty-message="__('admin.revenue.tables.none')"
    >
        @foreach ($revenue->transactions() as $row)
            <x-admin.table.row>
                <x-admin.table.cell primary><span dir="ltr">{{ $row['when'] }}</span></x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.revenue.tables.type')">{{ $row['type'] }}</x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.revenue.tables.amount')" :class="$row['refund'] ? 'font-bold text-red-700' : 'font-bold'"><span dir="ltr">{{ $row['amount'] }}</span></x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.revenue.tables.subject')" class="text-ink-700">{{ $row['subject'] ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.revenue.tables.open')">
                    <span class="flex flex-wrap gap-x-3 gap-y-1">
                        @foreach ($row['links'] as $link)
                            <a class="text-xs font-semibold text-coral-700 hover:underline" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                        @endforeach
                    </span>
                </x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>
    <x-admin.pagination :paginator="$revenue->paginator()" class="!mt-0" />
</section>
