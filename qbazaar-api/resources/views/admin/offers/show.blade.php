@extends('admin.layout')

@section('title', __('admin.offer_admin.crumb', ['id' => $offer->id]))
@section('heading', __('admin.navigation.offers'))

@section('content')
    <x-admin.page :title="__('admin.offer_admin.title', ['ad' => $offer->ad?->title ?? __('admin.offer_admin.deleted_ad')])"
                  :crumbs="[[__('admin.navigation.offers'), route('admin.offers.index')], __('admin.offer_admin.crumb', ['id' => $offer->id])]"
                  :subtitle="__('admin.offer_admin.subtitle', ['date' => $offer->created_at->format('Y-m-d H:i')])"
                  :side-label="__('admin.offer_admin.side_label')">
        <x-slot:badges>
            <x-admin.badge :status="$offer->status" />
            @if ($order)
                <x-admin.badge :tone="$orderTone">{{ __('admin.deal.order_status.' . $order->status->value) }}</x-admin.badge>
            @endif
        </x-slot:badges>

        <x-slot:actions>
            @if ($canOpenChat)
                <x-admin.button variant="secondary" icon="chat" :href="route('admin.conversations.show', $offer->conversation_id)">{{ __('admin.offer_admin.details.open_chat') }}</x-admin.button>
            @endif
            @if ($canOpenAd)
                <x-admin.button variant="secondary" icon="eye" :href="route('admin.ads.show', $offer->ad_id)">{{ __('admin.offer_admin.details.open_ad') }}</x-admin.button>
            @endif
        </x-slot:actions>

        @if ($errors->any())
            <x-slot:lead>
                <x-admin.alert tone="danger" :title="__('admin.shell.error_summary')" :summary="$errors" autofocus />
            </x-slot:lead>
        @endif

        {{-- Main column --}}
        <x-admin.form-section id="offer-details" :title="__('admin.offer_admin.details.title')" icon="banknotes">
            <dl class="grid gap-x-6 gap-y-4 @min-[34rem]:grid-cols-2 @min-[46rem]:grid-cols-3">
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">{{ __('admin.offer_admin.details.amount') }}</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-900"><span dir="ltr" class="tabular-nums">{{ number_format((float) $offer->amount, 2) }} {{ $offer->currency ?: __('admin.deal.currency') }}</span></dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">{{ __('admin.offer_admin.details.status') }}</dt>
                    <dd class="mt-0.5"><x-admin.badge :status="$offer->status" /></dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">{{ __('admin.offer_admin.details.proposed_by') }}</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-900">{{ __('admin.offer_admin.details.' . $offer->proposed_by->value) }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">{{ __('admin.offer_admin.details.round') }}</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-900 tabular-nums">{{ $offer->counter_round + 1 }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">{{ __('admin.offer_admin.details.expires_at') }}</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-900"><span dir="ltr">{{ $offer->expires_at->format('Y-m-d H:i') }}</span></dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">{{ __('admin.offer_admin.details.decided_at') }}</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-900"><span dir="ltr">{{ ($offer->accepted_at ?? $offer->rejected_at ?? $offer->withdrawn_at ?? $offer->countered_at)?->format('Y-m-d H:i') ?? '—' }}</span></dd>
                </div>
            </dl>

            @if ($offer->note)
                <div>
                    <h3 class="mb-1.5 text-[13px] font-semibold text-ink-500">{{ __('admin.offer_admin.details.note') }}</h3>
                    <p class="max-w-[70ch] text-sm leading-[1.9] whitespace-pre-line break-words text-ink-700">{{ $offer->note }}</p>
                </div>
            @endif

            @if ($offer->parentOffer)
                <p class="text-[13px] text-ink-500">
                    {{ __('admin.offer_admin.details.parent') }}:
                    <a href="{{ route('admin.offers.show', $offer->parentOffer) }}" class="font-semibold text-coral-700 hover:underline">
                        <span dir="ltr" class="tabular-nums">{{ number_format((float) $offer->parentOffer->amount, 2) }} {{ $offer->parentOffer->currency ?: __('admin.deal.currency') }}</span>
                    </a>
                    <x-admin.badge :status="$offer->parentOffer->status" class="ms-1" />
                </p>
            @endif
        </x-admin.form-section>

        <x-admin.form-section id="offer-order" :title="__('admin.offer_admin.order.title')" icon="banknotes">
            @if ($order === null)
                <x-admin.empty-state compact icon="inbox" :message="__('admin.offer_admin.order.none')" />
            @else
                <x-admin.meta-list class="max-w-xl">
                    <x-admin.meta-item :label="__('admin.offer_admin.order.status')">{{ __('admin.deal.order_status.' . $order->status->value) }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.offer_admin.order.total')" ltr>{{ $order->total }} {{ $order->currency }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.offer_admin.order.payment')">{{ __('admin.deal.method.' . $order->payment_method->value) }}</x-admin.meta-item>
                </x-admin.meta-list>

                @if ($canOpenOrder)
                    <div>
                        <x-admin.button variant="secondary" size="sm" icon="external" :href="route('admin.finance.orders.show', $order)">{{ __('admin.offer_admin.order.open') }}</x-admin.button>
                    </div>
                @endif

                @if ($order->disputed_at)
                    <div class="grid gap-3 border-t border-ink-200 pt-4">
                        <h3 class="text-[13px] font-semibold text-ink-700">{{ __('admin.offer_admin.dispute.title') }}</h3>
                        <x-admin.meta-list class="max-w-xl">
                            <x-admin.meta-item :label="__('admin.offer_admin.dispute.reason')">{{ $order->dispute_reason ?: '—' }}</x-admin.meta-item>
                            <x-admin.meta-item :label="__('admin.offer_admin.dispute.opened_at')" ltr>{{ $order->disputed_at->format('Y-m-d H:i') }}</x-admin.meta-item>
                            @if ($order->dispute_resolution)
                                <x-admin.meta-item :label="__('admin.offer_admin.dispute.resolution')">{{ __('admin.deal.ruling.' . $order->dispute_resolution->value) }}</x-admin.meta-item>
                                <x-admin.meta-item :label="__('admin.offer_admin.dispute.resolution_note')">{{ $order->dispute_resolution_note ?: '—' }}</x-admin.meta-item>
                                <x-admin.meta-item :label="__('admin.offer_admin.dispute.resolved_at')" ltr>{{ $order->dispute_resolved_at?->format('Y-m-d H:i') ?? '—' }}</x-admin.meta-item>
                            @endif
                        </x-admin.meta-list>

                        @if ($canResolveDispute)
                            @include('admin.finance.partials.dispute-ruling-form', ['order' => $order, 'class' => 'grid max-w-md gap-2'])
                        @endif
                    </div>
                @endif
            @endif
        </x-admin.form-section>

        {{-- Side column --}}
        <x-slot:side>
            @if ($canCancel)
                <x-admin.side-card id="offer-cancel" :title="__('admin.offer_admin.cancel.title')" icon="shield">
                    <x-admin.sanction-form prefix="offer-cancel" icon="x-circle" :action="route('admin.offers.cancel', $offer)"
                                           :label="__('admin.offer_admin.cancel.submit')" :confirm="__('admin.offer_admin.cancel.confirm')"
                                           :intro="__('admin.offer_admin.cancel.intro')" />
                </x-admin.side-card>
            @endif

            <x-admin.side-card id="offer-parties" :title="__('admin.offer_admin.parties.title')" icon="users">
                <x-admin.meta-list>
                    <x-admin.meta-item :label="__('admin.offer_admin.details.buyer')"><x-admin.user :user="$offer->buyer" size="xs" :fallback="__('admin.offer_admin.deleted_account')" /></x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.offer_admin.details.seller')"><x-admin.user :user="$offer->seller" size="xs" :fallback="__('admin.offer_admin.deleted_account')" /></x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>

            <x-admin.side-card id="offer-history" :title="__('admin.offer_admin.history')" icon="activity">
                @if ($timeline === null)
                    <x-admin.error-state :retry-url="request()->fullUrl()" />
                @else
                    <x-admin.timeline :entries="$timeline" />
                @endif
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
