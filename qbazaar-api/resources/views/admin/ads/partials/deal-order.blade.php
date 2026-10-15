{{-- The order: tracker, amounts and key dates. Needs $deal (OrderDeal); $onAd adds the link to the order page. --}}
@php
    $order = $deal->order;
    $money = fn (string $amount): string => \App\Support\Money::format($amount) . ' ' . __('admin.deal.currency');
    $statusTones = ['created' => 'neutral', 'awaiting_handover' => 'info', 'completed' => 'success', 'cancelled' => 'danger', 'disputed' => 'warning'];
    $amounts = [
        [__('admin.deal.order.unit_price'), $money($order->unit_price) . ($order->quantity > 1 ? ' × ' . $order->quantity : ''), true],
        [__('admin.deal.order.shipping_fee'), \App\Support\Money::isPositive($order->shipping_fee) ? $money($order->shipping_fee) : null, true],
        [__('admin.deal.order.total'), $money($order->total), true],
        [__('admin.deal.order.commission', ['rate' => $order->commission_rate]), $money($order->commission_amount), true],
        [__('admin.deal.order.seller_net'), $money($deal->payment->sellerNet), true],
        [__('admin.deal.order.payment_method'), __('admin.deal.method.' . $order->payment_method->value), false],
        [__('admin.deal.order.fulfillment'), $order->fulfillment ? __('admin.deal.fulfillment.' . $order->fulfillment->value) : null, false],
    ];
    $dates = [
        [__('admin.deal.events.created'), $order->created_at],
        [__('admin.deal.events.awaiting_handover'), $order->awaiting_handover_at],
        [__('admin.deal.events.handed_over'), $order->handed_over_at],
        [__('admin.deal.events.completed'), $order->completed_at],
        [__('admin.deal.events.cancelled'), $order->cancelled_at],
        [__('admin.deal.events.disputed'), $order->disputed_at],
        [__('admin.deal.events.dispute_resolved'), $order->dispute_resolved_at],
    ];
@endphp

<x-admin.form-section id="ad-order" :title="__('admin.deal.order.title')" icon="banknotes"
                      :description="__('admin.deal.order.number', ['id' => strtoupper(substr($order->id, -8))])">
    <x-slot:actions>
        <x-admin.badge :tone="$statusTones[$order->status->value]">{{ __('admin.deal.order_status.' . $order->status->value) }}</x-admin.badge>
        @if ($onAd ?? false)
            <x-admin.button variant="secondary" size="sm" :href="route('admin.finance.orders.show', $order)" icon="arrow-right">{{ __('admin.deal.order.open') }}</x-admin.button>
        @endif
    </x-slot:actions>

    @include('admin.ads.partials.deal-steps', ['steps' => $deal->steps])

    <dl class="grid gap-x-6 gap-y-4 @min-[34rem]:grid-cols-2 @min-[46rem]:grid-cols-3">
        @foreach ($amounts as [$label, $value, $ltr])
            @if ($value !== null)
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">{{ $label }}</dt>
                    <dd class="mt-0.5 text-sm font-semibold break-words text-ink-900"><span @if ($ltr) dir="ltr" @endif>{{ $value }}</span></dd>
                </div>
            @endif
        @endforeach
    </dl>

    <div>
        <h3 class="mb-2 text-[13px] font-semibold text-ink-500">{{ __('admin.deal.order.key_dates') }}</h3>
        <x-admin.meta-list class="gap-x-6 gap-y-2 text-[13px] @min-[34rem]:grid-cols-2">
            @foreach ($dates as [$label, $at])
                @if ($at)
                    <x-admin.meta-item :label="$label" ltr>{{ $at->format('Y-m-d H:i') }}</x-admin.meta-item>
                @endif
            @endforeach
        </x-admin.meta-list>
    </div>

    @if ($order->cancellation_reason)
        <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700"><span class="font-bold">{{ __('admin.deal.order.cancellation_reason') }}:</span> {{ $order->cancellation_reason }}</p>
    @endif
    @if ($order->dispute_reason)
        <div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p><span class="font-bold">{{ __('admin.deal.order.dispute_reason') }}:</span> {{ $order->dispute_reason }}</p>
            @if ($order->dispute_resolution)
                <p class="mt-1"><span class="font-bold">{{ __('admin.deal.order.ruling') }}:</span> {{ __('admin.deal.ruling.' . $order->dispute_resolution->value) }}@if ($order->dispute_resolution_note) — {{ $order->dispute_resolution_note }}@endif</p>
            @endif
        </div>
    @endif

    @if ($deal->otherOrders > 0)
        <p class="text-[13px] text-ink-500">{{ __('admin.deal.order.other_orders', ['count' => $deal->otherOrders]) }}</p>
    @endif
</x-admin.form-section>
