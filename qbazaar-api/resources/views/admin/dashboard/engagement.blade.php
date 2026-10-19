{{-- Messages, new chats and follows over 30 days (AD-17.6), one small chart each; built by DashboardLayout::engagementCharts(). --}}
@if ($home->engagementCharts() !== [])
    <x-admin.card :title="__('admin.home.engagement.title')" icon="chat" id="engagement" :description="__('admin.home.engagement.caption', ['period' => $period->label()])">
        <div class="grid grid-cols-1 gap-x-8 gap-y-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($home->engagementCharts() as $chart)
                <div class="min-w-0" data-engagement="{{ $chart['key'] }}">
                    <div class="mb-2 flex items-baseline justify-between gap-3">
                        <h3 class="text-sm font-semibold text-ink-700">{{ $chart['label'] }}</h3>
                        @if ($chart['value'] !== null)
                            <p class="flex items-center gap-2">
                                @if ($chart['change'] !== false)<x-admin.trend :change="$chart['change']" />@endif
                                <span dir="ltr" class="text-sm font-bold">{{ $chart['value'] }}</span>
                            </p>
                        @endif
                    </div>
                    <x-admin.chart.lines height="h-36" :label="$chart['label']" :series="$chart['series']" />
                </div>
            @endforeach
        </div>
    </x-admin.card>
@endif
