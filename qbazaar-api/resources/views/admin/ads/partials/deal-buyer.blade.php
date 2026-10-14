{{-- Side column, under the seller: who bought. Needs $deal (AdTransaction|null). --}}
@php
    $basis = $deal?->buyerBasis;
    $buyer = $deal?->buyer;
    $visible = $deal?->showDeal
        && $basis !== \App\Data\Admin\AdDeal\BuyerBasis::UNKNOWN
        && ($basis === \App\Data\Admin\AdDeal\BuyerBasis::OFF_PLATFORM || $deal->access->users);
@endphp

@if ($visible)
    <x-admin.side-card id="ad-buyer" :title="__('admin.deal.buyer.title')" icon="users"
                       :link="$buyer ? [__('admin.deal.buyer.open'), route('admin.users.show', $buyer)] : null">
        @if ($buyer)
            @if ($basis === \App\Data\Admin\AdDeal\BuyerBasis::ACCEPTED_OFFER)
                <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-[13px] font-semibold text-amber-800">{{ __('admin.deal.buyer.likely') }}</p>
            @endif
            <div class="flex items-center gap-3">
                <x-admin.user-avatar :user="$buyer" size="md" decorative />
                <div class="min-w-0">
                    <p class="truncate font-bold text-ink-900" title="{{ $buyer->full_name }}">{{ $buyer->full_name ?: $buyer->email }}</p>
                    @if ($buyer->phone)
                        <x-admin.phone :phone="$buyer->phone" class="text-[13px] text-ink-500" />
                    @endif
                </div>
            </div>
            <x-admin.meta-list class="mt-4">
                <x-admin.meta-item :label="__('admin.deal.buyer.email')" ltr>{{ $buyer->email }}</x-admin.meta-item>
                <x-admin.meta-item :label="__('admin.deal.buyer.status')"><x-admin.badge :status="$buyer->status" /></x-admin.meta-item>
                <x-admin.meta-item :label="__('admin.deal.buyer.since')" ltr>{{ $buyer->created_at->format('Y-m-d') }}</x-admin.meta-item>
            </x-admin.meta-list>
        @elseif ($basis === \App\Data\Admin\AdDeal\BuyerBasis::ORDER)
            <p class="text-ink-500">{{ __('admin.deal.buyer.deleted') }}</p>
        @endif

        @if ($basis === \App\Data\Admin\AdDeal\BuyerBasis::OFF_PLATFORM || ($basis === \App\Data\Admin\AdDeal\BuyerBasis::ACCEPTED_OFFER && $deal->access->finance))
            <p @class(['text-[13px] font-semibold text-ink-700', 'mt-4 border-t border-ink-200 pt-3' => $buyer])>{{ __('admin.deal.buyer.off_platform') }}</p>
            @if ($basis === \App\Data\Admin\AdDeal\BuyerBasis::OFF_PLATFORM)
                <p class="mt-1 text-[13px] text-ink-500">{{ __('admin.deal.buyer.off_platform_hint') }}</p>
            @endif
        @endif
    </x-admin.side-card>
@endif
