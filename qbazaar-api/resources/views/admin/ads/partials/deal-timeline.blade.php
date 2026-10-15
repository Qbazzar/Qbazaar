{{-- The deal's merged timeline. Needs $deal (OrderDeal). --}}
<x-admin.form-section id="ad-deal-timeline" :title="__('admin.deal.timeline.title')" icon="activity"
                      :description="__('admin.deal.timeline.description', ['count' => \App\Services\Admin\Ads\DealTimeline::LIMIT])">
    <x-admin.timeline :entries="$deal->timeline" :empty="__('admin.deal.timeline.empty')" />
</x-admin.form-section>
