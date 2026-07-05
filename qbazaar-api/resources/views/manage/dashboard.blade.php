@extends('manage.layout')

@section('title', 'لوحة القيادة')
@section('heading', 'لوحة القيادة')

@section('content')
    @php($cards = [
        ['label' => 'إجمالي الإعلانات', 'value' => $stats['ads_total']],
        ['label' => 'إعلانات نشطة', 'value' => $stats['ads_active']],
        ['label' => 'بانتظار المراجعة', 'value' => $stats['ads_pending']],
        ['label' => 'المستخدمون', 'value' => $stats['users_total']],
        ['label' => 'بلاغات معلّقة', 'value' => $stats['reports_pending']],
    ])

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
        @foreach ($cards as $card)
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-5">
                <div class="text-sm font-medium text-ink-500">{{ $card['label'] }}</div>
                <div class="mt-2 text-3xl font-extrabold">{{ number_format($card['value']) }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-8 rounded-2xl border border-ink-200 bg-cream-100">
        <div class="flex items-center justify-between border-b border-ink-200 px-6 py-4">
            <h2 class="font-bold">إعلانات بانتظار المراجعة</h2>
            <a href="{{ route('manage.ads.index', ['status' => 'pending']) }}" class="text-sm font-semibold text-coral">عرض الكل</a>
        </div>

        @if ($pendingAds->isEmpty())
            <p class="px-6 py-10 text-center text-sm text-ink-500">لا توجد إعلانات بانتظار المراجعة.</p>
        @else
            <ul class="divide-y divide-ink-200">
                @foreach ($pendingAds as $ad)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div class="min-w-0">
                            <a href="{{ route('manage.ads.show', $ad) }}" class="block truncate font-semibold hover:text-coral">{{ $ad->title }}</a>
                            <div class="mt-0.5 text-xs text-ink-500">
                                {{ $ad->user?->full_name ?? '—' }} · {{ optional($ad->created_at)->diffForHumans() }}
                            </div>
                        </div>
                        <a href="{{ route('manage.ads.show', $ad) }}" class="shrink-0 rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">مراجعة</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
