@extends('admin.layout')

@section('title', 'لوحة القيادة')
@section('heading', 'لوحة القيادة')

@section('content')
    @php($max = max(1, collect($trend)->max('count')))

    <section aria-labelledby="dashboard-ads">
        <h2 id="dashboard-ads" class="mb-3 flex items-center gap-2 text-sm font-bold text-ink-500">
            <x-admin.icon name="tag" class="size-4" /> الإعلانات
        </h2>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <x-admin.stat label="نشطة" :value="number_format($ads['active'])" icon="check" :href="route('admin.ads.index', ['status' => 'active'])" />
            <x-admin.stat label="بانتظار المراجعة" :value="number_format($ads['pending'])" icon="clock" :href="route('admin.ads.index', ['status' => 'pending'])" />
            <x-admin.stat label="نُشرت اليوم" :value="number_format($ads['published_today'])" icon="trend" />
            <x-admin.stat label="الإجمالي" :value="number_format($ads['total'])" icon="tag" :href="route('admin.ads.index')" />
        </div>
    </section>

    <section aria-labelledby="dashboard-users" class="mt-8">
        <h2 id="dashboard-users" class="mb-3 flex items-center gap-2 text-sm font-bold text-ink-500">
            <x-admin.icon name="users" class="size-4" /> المستخدمون
        </h2>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <x-admin.stat label="الإجمالي" :value="number_format($users['total'])" />
            <x-admin.stat label="نشطون" :value="number_format($users['active'])" />
            <x-admin.stat label="نشطون اليوم" :value="number_format($users['active_today'])" />
            <x-admin.stat label="جدد هذا الأسبوع" :value="number_format($users['new_week'])" />
        </div>
    </section>

    <section aria-labelledby="dashboard-reports" class="mt-8">
        <h2 id="dashboard-reports" class="mb-3 flex items-center gap-2 text-sm font-bold text-ink-500">
            <x-admin.icon name="flag" class="size-4" /> البلاغات
        </h2>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
            <x-admin.stat label="معلّقة" :value="number_format($reports['pending'])" />
            <x-admin.stat label="تم اتخاذ إجراء (الأسبوع)" :value="number_format($reports['actioned_week'])" />
            <x-admin.stat label="مرفوضة (الأسبوع)" :value="number_format($reports['dismissed_week'])" />
        </div>
    </section>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        {{-- CSS bars, no chart library. --}}
        <x-admin.card title="الإعلانات المنشورة — آخر ١٤ يوماً" icon="trend">
            <div class="flex h-40 items-end gap-1.5" role="img"
                 aria-label="{{ collect($trend)->map(fn ($point) => $point['label'] . ': ' . $point['count'])->implode('، ') }}">
                @foreach ($trend as $point)
                    <div class="flex flex-1 flex-col items-center gap-1" title="{{ $point['label'] }}: {{ $point['count'] }}">
                        <div class="w-full rounded-t bg-coral-soft" style="height: {{ max(2, (int) round($point['count'] / $max * 130)) }}px">
                            <div class="size-full rounded-t bg-coral opacity-80"></div>
                        </div>
                        <span class="text-[10px] text-ink-500" aria-hidden="true">{{ $point['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </x-admin.card>

        <x-admin.card title="بانتظار المراجعة" flush>
            <x-slot:actions>
                <a href="{{ route('admin.ads.index', ['status' => 'pending']) }}" class="rounded-lg text-sm font-semibold text-coral-700 hover:underline">عرض الكل</a>
            </x-slot:actions>

            @if ($pendingAds->isEmpty())
                <p class="px-6 py-10 text-center text-sm text-ink-500">لا توجد إعلانات بانتظار المراجعة.</p>
            @else
                <ul class="divide-y divide-ink-200">
                    @foreach ($pendingAds as $ad)
                        <li class="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-6">
                            <div class="min-w-0">
                                <a href="{{ route('admin.ads.show', $ad) }}" class="block truncate font-semibold hover:text-coral-700">{{ $ad->title }}</a>
                                <div class="mt-0.5 text-xs text-ink-500">{{ $ad->user?->full_name ?? '—' }} · {{ optional($ad->created_at)->diffForHumans() }}</div>
                            </div>
                            <x-admin.button variant="secondary" size="sm" :href="route('admin.ads.show', $ad)">مراجعة</x-admin.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-admin.card>
    </div>
@endsection
