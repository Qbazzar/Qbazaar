{{-- Main column, top of the ad page: the order, its payment and the deal timeline of a sold or reserved ad. Needs $deal (AdTransaction|null). --}}
@if ($deal?->showDeal)
    @if ($deal->deal)
        @include('admin.ads.partials.deal-order', ['deal' => $deal->deal, 'onAd' => true])
        @include('admin.ads.partials.deal-payment', ['deal' => $deal->deal])
        @include('admin.ads.partials.deal-timeline', ['deal' => $deal->deal])
    @elseif ($deal->access->finance)
        <x-admin.form-section id="ad-order" :title="__('admin.deal.order.title')" icon="banknotes">
            <x-admin.empty-state compact icon="banknotes" :message="__('admin.deal.order.none')" />
        </x-admin.form-section>
    @endif
@endif
