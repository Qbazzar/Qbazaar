@extends('manage.layout')

@section('title', 'لوحة القيادة')
@section('heading', 'لوحة القيادة')

@section('content')
    @php($max = max(1, collect($trend)->max('count')))

    {{-- Ads --}}
    <div class="mb-3 flex items-center gap-2 text-sm font-bold text-ink-500">
        <x-manage.icon name="tag" class="size-4" /> الإعلانات
    </div>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ([
            ['نشطة', $ads['active'], 'check', 'manage.ads.index', ['status' => 'active']],
            ['بانتظار المراجعة', $ads['pending'], 'clock', 'manage.ads.index', ['status' => 'pending']],
            ['نُشرت اليوم', $ads['published_today'], 'trend', null, null],
            ['الإجمالي', $ads['total'], 'tag', 'manage.ads.index', []],
        ] as [$label, $value, $icon, $route, $params])
            <a @if($route) href="{{ route($route, $params) }}" @endif
               class="group block rounded-2xl border border-ink-200 bg-cream-100 p-5 {{ $route ? 'transition hover:border-coral hover:shadow-sm' : '' }}">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-ink-500">{{ $label }}</div>
                    <span class="flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-500 {{ $route ? 'group-hover:bg-coral-soft group-hover:text-coral' : '' }}">
                        <x-manage.icon :name="$icon" class="size-[18px]" />
                    </span>
                </div>
                <div class="mt-2 text-3xl font-extrabold">{{ number_format($value) }}</div>
            </a>
        @endforeach
    </div>

    {{-- Users --}}
    <div class="mb-3 mt-8 flex items-center gap-2 text-sm font-bold text-ink-500">
        <x-manage.icon name="users" class="size-4" /> المستخدمون
    </div>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ([
            ['الإجمالي', $users['total']],
            ['نشطون', $users['active']],
            ['نشطون اليوم', $users['active_today']],
            ['جدد هذا الأسبوع', $users['new_week']],
        ] as [$label, $value])
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-5">
                <div class="text-sm font-medium text-ink-500">{{ $label }}</div>
                <div class="mt-2 text-3xl font-extrabold">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    {{-- Reports --}}
    <div class="mb-3 mt-8 flex items-center gap-2 text-sm font-bold text-ink-500">
        <x-manage.icon name="flag" class="size-4" /> البلاغات
    </div>
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
        @foreach ([
            ['معلّقة', $reports['pending']],
            ['تم اتخاذ إجراء (الأسبوع)', $reports['actioned_week']],
            ['مرفوضة (الأسبوع)', $reports['dismissed_week']],
        ] as [$label, $value])
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-5">
                <div class="text-sm font-medium text-ink-500">{{ $label }}</div>
                <div class="mt-2 text-3xl font-extrabold">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        {{-- Published trend (CSS bars — no JS) --}}
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="mb-4 flex items-center gap-2 font-bold"><x-manage.icon name="trend" class="size-5 text-coral" /> الإعلانات المنشورة — آخر ١٤ يوماً</h2>
            <div class="flex h-40 items-end gap-1.5">
                @foreach ($trend as $point)
                    <div class="flex flex-1 flex-col items-center gap-1" title="{{ $point['label'] }}: {{ $point['count'] }}">
                        <div class="w-full rounded-t bg-coral-soft" style="height: {{ max(2, (int) round($point['count'] / $max * 130)) }}px">
                            <div class="h-full w-full rounded-t bg-coral opacity-80"></div>
                        </div>
                        <span class="text-[10px] text-ink-500">{{ $point['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Pending queue --}}
        <div class="rounded-2xl border border-ink-200 bg-cream-100">
            <div class="flex items-center justify-between border-b border-ink-200 px-6 py-4">
                <h2 class="font-bold">بانتظار المراجعة</h2>
                <a href="{{ route('manage.ads.index', ['status' => 'pending']) }}" class="text-sm font-semibold text-coral">عرض الكل</a>
            </div>
            @if ($pendingAds->isEmpty())
                <p class="px-6 py-10 text-center text-sm text-ink-500">لا توجد إعلانات بانتظار المراجعة.</p>
            @else
                <ul class="divide-y divide-ink-200">
                    @foreach ($pendingAds as $ad)
                        <li class="flex items-center justify-between px-6 py-3.5">
                            <div class="min-w-0">
                                <a href="{{ route('manage.ads.show', $ad) }}" class="block truncate font-semibold hover:text-coral">{{ $ad->title }}</a>
                                <div class="mt-0.5 text-xs text-ink-500">{{ $ad->user?->full_name ?? '—' }} · {{ optional($ad->created_at)->diffForHumans() }}</div>
                            </div>
                            <a href="{{ route('manage.ads.show', $ad) }}" class="shrink-0 rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">مراجعة</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection
