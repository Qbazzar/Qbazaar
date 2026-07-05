@extends('manage.layout')

@section('title', 'الدعم الفني')
@section('heading', 'الدعم الفني')

@php
    $statusStyles = [
        'open' => 'bg-amber-50 text-amber-700',
        'in_progress' => 'bg-sky-50 text-sky-700',
        'waiting_user' => 'bg-violet-50 text-violet-700',
        'resolved' => 'bg-emerald-50 text-emerald-700',
        'closed' => 'bg-cream-200 text-ink-500',
    ];
    $statusLabels = [
        'open' => 'مفتوحة',
        'in_progress' => 'قيد المعالجة',
        'waiting_user' => 'بانتظار المستخدم',
        'resolved' => 'تم الحل',
        'closed' => 'مغلقة',
    ];
    $priorityStyles = [
        'low' => 'bg-cream-200 text-ink-500',
        'normal' => 'bg-sky-50 text-sky-700',
        'high' => 'bg-amber-50 text-amber-700',
        'urgent' => 'bg-red-50 text-red-700',
    ];
    $priorityLabels = [
        'low' => 'منخفضة',
        'normal' => 'عادية',
        'high' => 'مرتفعة',
        'urgent' => 'عاجلة',
    ];
@endphp

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالموضوع أو المستخدم…"
               class="w-64 rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">

        <select name="status" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل الحالات</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $statusLabels[$case->value] ?? $case->value }}</option>
            @endforeach
        </select>

        <select name="priority" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل الأولويات</option>
            @foreach ($priorities as $case)
                <option value="{{ $case->value }}" @selected($priority === $case->value)>{{ $priorityLabels[$case->value] ?? $case->value }}</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تصفية</button>
        @if ($search !== '' || $status !== '' || $priority !== '')
            <a href="{{ route('manage.support.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">الموضوع</th>
                        <th class="px-4 py-3 font-semibold">المستخدم</th>
                        <th class="px-4 py-3 font-semibold">الحالة</th>
                        <th class="px-4 py-3 font-semibold">الأولوية</th>
                        <th class="px-4 py-3 font-semibold">الردود</th>
                        <th class="px-4 py-3 font-semibold">آخر رد</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($tickets as $ticket)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('manage.support.show', $ticket) }}" class="font-semibold hover:text-coral">{{ \Illuminate\Support\Str::limit($ticket->subject, 60) }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $ticket->user?->full_name ?? $ticket->email ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$ticket->status->value] ?? 'bg-cream-200 text-ink-500' }}">
                                    {{ $statusLabels[$ticket->status->value] ?? $ticket->status->value }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $priorityStyles[$ticket->priority->value] ?? 'bg-cream-200 text-ink-500' }}">
                                    {{ $priorityLabels[$ticket->priority->value] ?? $ticket->priority->value }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ $ticket->replies_count }}</td>
                            <td class="px-4 py-3 text-ink-500">{{ $ticket->last_replied_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-left">
                                <a href="{{ route('manage.support.show', $ticket) }}" class="rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">عرض</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-ink-500">لا توجد تذاكر مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $tickets->links() }}
    </div>
@endsection
