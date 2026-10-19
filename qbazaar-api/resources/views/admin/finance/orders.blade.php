@extends('admin.layout')

@section('title', __('admin.orders_admin.title'))
@section('heading', __('admin.orders_admin.title'))

@section('content')
    <x-admin.page-toolbar :description="__('admin.orders_admin.subtitle')">
        <x-admin.filter-bar
            :reset-url="route('admin.finance.orders.index')"
            :search="$search"
            :placeholder="__('admin.orders_admin.search')"
            :filters="[[
                'name' => 'status',
                'label' => __('admin.orders_admin.columns.status'),
                'placeholder' => __('admin.orders_admin.all_statuses'),
                'value' => $status?->value,
                'options' => $statusOptions,
            ]]"
        />
        @if ($searchIgnored)
            <p class="mt-3 text-sm text-ink-500">{{ __('admin.orders_admin.search_ignored') }}</p>
        @endif
    </x-admin.page-toolbar>

    <x-admin.table
        :caption="__('admin.orders_admin.title')"
        :columns="[
            __('admin.orders_admin.columns.order'),
            __('admin.orders_admin.columns.buyer'),
            __('admin.orders_admin.columns.seller'),
            __('admin.orders_admin.columns.total'),
            ['label' => __('admin.orders_admin.columns.commission'), 'secondary' => true],
            __('admin.orders_admin.columns.status'),
            ['label' => __('admin.orders_admin.columns.date'), 'secondary' => true],
        ]"
        :empty="$orders->isEmpty()"
        empty-icon="banknotes"
        :empty-message="__('admin.orders_admin.empty')"
    >
        @foreach ($orders as $order)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.finance.orders.show', $order) }}" class="font-semibold text-ink-900 hover:text-coral-700">{{ $order->ad_title }}</a>
                    <span class="block font-mono text-[11px] text-ink-500" dir="ltr">{{ $order->id }}</span>
                    <span class="block text-xs text-ink-500">{{ __('admin.deal.method.' . $order->payment_method->value) }}</span>
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.orders_admin.columns.buyer')">
                    <x-admin.user :user="$order->buyer" size="xs" :fallback="__('admin.conversation_access.deleted_account')" />
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.orders_admin.columns.seller')">
                    <x-admin.user :user="$order->seller" size="xs" :fallback="__('admin.conversation_access.deleted_account')" />
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.orders_admin.columns.total')" class="font-bold">
                    <span dir="ltr">{{ \App\Support\Money::format((string) $order->total) }} {{ $order->currency }}</span>
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.orders_admin.columns.commission')" secondary class="text-ink-700">
                    <span dir="ltr">{{ \App\Support\Money::format((string) $order->commission_amount) }} · {{ $order->commission_rate }}%</span>
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.orders_admin.columns.status')">
                    <x-admin.badge :tone="$order->status->badgeTone()">{{ __('admin.deal.order_status.' . $order->status->value) }}</x-admin.badge>
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.orders_admin.columns.date')" secondary class="text-ink-500">
                    <span dir="ltr">{{ $order->created_at->format('Y-m-d H:i') }}</span>
                </x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$orders" />
@endsection
