@extends('admin.layout')

@section('title', 'العروض')
@section('heading', 'العروض')

@php
    $offerStatusStyles = [
        'pending' => 'bg-amber-50 text-amber-700',
        'accepted' => 'bg-emerald-50 text-emerald-700',
        'rejected' => 'bg-red-50 text-red-700',
        'withdrawn' => 'bg-cream-200 text-ink-500',
        'expired' => 'bg-cream-200 text-ink-500',
    ];
    $offerStatusLabels = [
        'pending' => 'قيد الانتظار',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'withdrawn' => 'مسحوب',
        'expired' => 'منتهٍ',
    ];
@endphp

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                <x-admin.icon name="search" class="size-[18px]" />
            </span>
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالإعلان أو المشتري…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
        </div>

        <select name="status" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل الحالات</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $offerStatusLabels[$case->value] ?? $case->value }}</option>
            @endforeach
        </select>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="filter" class="size-[18px]" /> تصفية
        </button>
        @if ($search !== '' || $status !== '')
            <a href="{{ route('admin.offers.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">الإعلان</th>
                        <th class="px-4 py-3 font-semibold">من (مشتري)</th>
                        <th class="px-4 py-3 font-semibold">إلى (بائع)</th>
                        <th class="px-4 py-3 font-semibold">المبلغ</th>
                        <th class="px-4 py-3 font-semibold">الحالة</th>
                        <th class="px-4 py-3 font-semibold">التاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($offers as $offer)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($offer->ad?->title ?? '—', 40) }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $offer->buyer?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $offer->seller?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 font-semibold">{{ number_format((float) $offer->amount, 2) }} {{ $offer->currency ?: 'QAR' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $offerStatusStyles[$offer->status->value] ?? 'bg-cream-200 text-ink-500' }}">
                                    {{ $offerStatusLabels[$offer->status->value] ?? $offer->status->value }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($offer->created_at)->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-admin.icon name="banknotes" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد عروض مطابقة.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $offers->links() }}
    </div>
@endsection
