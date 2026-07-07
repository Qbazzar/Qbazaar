@extends('manage.layout')

@section('title', 'البلاغات')
@section('heading', 'البلاغات')

@php
    $statusStyles = [
        'pending' => 'bg-amber-50 text-amber-700',
        'reviewed' => 'bg-sky-50 text-sky-700',
        'dismissed' => 'bg-cream-200 text-ink-500',
        'actioned' => 'bg-emerald-50 text-emerald-700',
    ];
    $statusLabels = [
        'pending' => 'بانتظار المراجعة',
        'reviewed' => 'تمت المراجعة',
        'dismissed' => 'مرفوض',
        'actioned' => 'تم اتخاذ إجراء',
    ];
    $targetLabels = [
        'ad' => 'إعلان',
        'user' => 'مستخدم',
        'conversation' => 'محادثة',
        'message' => 'رسالة',
    ];
@endphp

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                <x-manage.icon name="search" class="size-[18px]" />
            </span>
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالمُبلِّغ أو الرقم…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
        </div>

        <select name="status" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل الحالات</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $statusLabels[$case->value] ?? $case->value }}</option>
            @endforeach
        </select>

        <select name="category" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل الأسباب</option>
            @foreach ($categories as $case)
                <option value="{{ $case->value }}" @selected($category === $case->value)>{{ $case->label()['ar'] }}</option>
            @endforeach
        </select>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-manage.icon name="filter" class="size-[18px]" /> تصفية
        </button>
        @if ($search !== '' || $status !== '' || $category !== '')
            <a href="{{ route('manage.reports.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    @include('manage.partials.bulk-bar', ['action' => route('manage.reports.bulk-dismiss'), 'method' => 'POST', 'icon' => 'x-circle', 'tone' => 'coral', 'label' => 'رفض المحدد', 'confirm' => 'رفض البلاغات المحددة (المعلّقة فقط)؟'])

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3"><input type="checkbox" id="qb-bulk-all" onclick="qbBulkAll(this)" class="rounded border-ink-300 text-coral"></th>
                        <th class="px-4 py-3 font-semibold">الهدف</th>
                        <th class="px-4 py-3 font-semibold">السبب</th>
                        <th class="px-4 py-3 font-semibold">المُبلِّغ</th>
                        <th class="px-4 py-3 font-semibold">الحالة</th>
                        <th class="px-4 py-3 font-semibold">التاريخ</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($reports as $report)
                        <tr class="transition hover:bg-cream-50">
                            <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $report->id }}" form="qb-bulk-form" class="qb-bulk-cb rounded border-ink-300 text-coral" onchange="qbBulkSync()"></td>
                            <td class="px-4 py-3">
                                <span class="font-semibold">{{ $targetLabels[$report->target_type->value] ?? $report->target_type->value }}</span>
                                <span class="block text-xs text-ink-500">{{ \Illuminate\Support\Str::limit($report->target_id, 12) }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $report->category->label()['ar'] }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $report->reporter?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$report->status->value] ?? 'bg-cream-200 text-ink-500' }}">
                                    {{ $statusLabels[$report->status->value] ?? $report->status->value }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($report->created_at)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('manage.reports.show', $report) }}" title="عرض"
                                       class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-coral-soft hover:text-coral">
                                        <x-manage.icon name="eye" class="size-[18px]" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-manage.icon name="flag" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد بلاغات مطابقة.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $reports->links() }}
    </div>
@endsection
