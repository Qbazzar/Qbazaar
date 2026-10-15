{{-- Payment, escrow and the seller's payout, read from the ledger. Needs $deal (OrderDeal). Finance staff only. --}}
@php
    $order = $deal->order;
    $payment = $deal->payment;
    $money = fn (string $amount): string => \App\Support\Money::format($amount) . ' ' . __('admin.deal.currency');
    $statusTones = ['pending' => 'warning', 'paid' => 'success', 'refunded' => 'info', 'reversed' => 'warning', 'not_charged' => 'neutral'];
    $escrowTones = ['none' => 'neutral', 'held' => 'warning', 'released' => 'success', 'refunded' => 'info'];
    $rows = [
        [__('admin.deal.payment.method'), __('admin.deal.method.' . $order->payment_method->value), false],
        [__('admin.deal.payment.amount'), $money($payment->total), true],
        [__('admin.deal.payment.seller_share'), $money($payment->sellerNet), true],
        [__('admin.deal.payment.commission'), $money($payment->commission), true],
    ];
@endphp

<x-admin.form-section id="ad-payment" :title="__('admin.deal.payment.title')" icon="shield" :description="__('admin.deal.payment.description')">
    <x-slot:actions>
        <x-admin.badge :tone="$statusTones[$payment->status]">{{ __('admin.deal.payment.status.' . $payment->status) }}</x-admin.badge>
    </x-slot:actions>

    <dl class="grid gap-x-6 gap-y-4 @min-[34rem]:grid-cols-2 @min-[46rem]:grid-cols-4">
        @foreach ($rows as [$label, $value, $ltr])
            <div class="min-w-0">
                <dt class="text-xs text-ink-500">{{ $label }}</dt>
                <dd class="mt-0.5 text-sm font-semibold break-words text-ink-900"><span @if ($ltr) dir="ltr" @endif>{{ $value }}</span></dd>
            </div>
        @endforeach
    </dl>

    <div class="grid gap-3 @min-[34rem]:grid-cols-2">
        <div class="rounded-xl bg-cream-50 px-4 py-3">
            <p class="text-xs text-ink-500">{{ __('admin.deal.payment.escrow') }}</p>
            <p class="mt-1 flex flex-wrap items-center gap-2">
                <x-admin.badge :tone="$escrowTones[$payment->escrow]">{{ __('admin.deal.payment.escrow_state.' . $payment->escrow) }}</x-admin.badge>
            </p>
            @if ($payment->escrowAt)
                <p class="mt-1.5 text-xs text-ink-500">
                    <time dir="ltr" datetime="{{ $payment->escrowAt->toIso8601String() }}">{{ $payment->escrowAt->format('Y-m-d H:i') }}</time>
                    @if ($payment->escrowActor) · {{ $payment->escrowActor }}@endif
                </p>
            @endif
        </div>
        <div class="rounded-xl bg-cream-50 px-4 py-3">
            <p class="text-xs text-ink-500">{{ __('admin.deal.payment.payout') }}</p>
            <p class="mt-1 text-sm font-semibold text-ink-900">{{ __('admin.deal.payment.payout_state.' . $payment->payout) }}</p>
            <p class="mt-1.5 text-xs text-ink-500">
                {{ __('admin.deal.payment.commission_state.' . ($payment->commissionRefunded ? 'refunded' : ($payment->commissionCharged ? 'charged' : 'none'))) }}
            </p>
        </div>
    </div>

    @if ($payment->lines !== [])
        <div>
            <h3 class="mb-2 text-[13px] font-semibold text-ink-500">{{ __('admin.deal.payment.postings') }}</h3>
            <ul role="list" class="grid gap-2 text-[13px]">
                @foreach ($payment->lines as $line)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 rounded-lg border border-ink-200 px-3 py-2">
                        <span class="font-bold text-ink-900">{{ __('admin.deal.ledger.' . $line->type->value) }}</span>
                        <span class="font-semibold text-ink-700" dir="ltr">{{ $money($line->amount) }}</span>
                        <span class="basis-full text-ink-500">
                            <time dir="ltr" datetime="{{ $line->at->toIso8601String() }}">{{ $line->at->format('Y-m-d H:i') }}</time>
                            @if ($line->actor) · {{ $line->actor }}@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <p class="text-[13px] text-ink-500">{{ __('admin.deal.payment.no_postings') }}</p>
    @endif

    <nav aria-label="{{ __('admin.deal.payment.links') }}" class="flex flex-wrap gap-2">
        @if ($order->seller_id)
            <x-admin.button variant="secondary" size="sm" icon="banknotes" :href="route('admin.finance.statements.show', $order->seller_id)">{{ __('admin.deal.payment.seller_statement') }}</x-admin.button>
        @endif
        @if ($order->buyer_id)
            <x-admin.button variant="secondary" size="sm" icon="banknotes" :href="route('admin.finance.statements.show', $order->buyer_id)">{{ __('admin.deal.payment.buyer_statement') }}</x-admin.button>
        @endif
        @if ($order->status === \App\Enums\OrderStatus::DISPUTED)
            <x-admin.button variant="secondary" size="sm" icon="exclamation" :href="route('admin.finance.disputes.index')">{{ __('admin.deal.payment.disputes') }}</x-admin.button>
        @endif
    </nav>
    @if (\App\Support\Money::isPositive($payment->sellerCommissionDebt))
        <p class="text-[13px] text-ink-500">{{ __('admin.deal.payment.seller_debt') }} <span dir="ltr" class="font-semibold text-ink-700">{{ $money($payment->sellerCommissionDebt) }}</span></p>
    @endif
</x-admin.form-section>
