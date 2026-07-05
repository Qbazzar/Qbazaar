@extends('manage.layout')

@section('title', 'تذكرة: ' . $ticket->subject)
@section('heading', 'مراجعة تذكرة')

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
    $priorityLabels = [
        'low' => 'منخفضة',
        'normal' => 'عادية',
        'high' => 'مرتفعة',
        'urgent' => 'عاجلة',
    ];
    $categoryLabels = [
        'general' => 'عام',
        'billing' => 'الفوترة',
        'technical' => 'تقني',
        'abuse' => 'إساءة',
        'feedback' => 'ملاحظات',
        'other' => 'أخرى',
    ];
@endphp

@section('content')
    <a href="{{ route('manage.support.index') }}" class="mb-4 inline-block text-sm font-semibold text-ink-500 hover:text-coral">→ رجوع</a>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Conversation --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-bold">{{ $ticket->subject }}</h2>
                    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$ticket->status->value] ?? 'bg-cream-200 text-ink-500' }}">
                        {{ $statusLabels[$ticket->status->value] ?? $ticket->status->value }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-ink-500">التصنيف:</span> {{ $categoryLabels[$ticket->category->value] ?? $ticket->category->value }}</div>
                    <div><span class="text-ink-500">الأولوية:</span> {{ $priorityLabels[$ticket->priority->value] ?? $ticket->priority->value }}</div>
                    <div><span class="text-ink-500">المسؤول:</span> {{ $ticket->assignee?->full_name ?? '—' }}</div>
                    <div><span class="text-ink-500">تاريخ الإنشاء:</span> {{ optional($ticket->created_at)->format('Y-m-d H:i') }}</div>
                </div>
            </div>

            {{-- Thread bubbles: user messages on the right, staff on the left --}}
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="mb-4 text-sm font-semibold text-ink-500">المحادثة</div>
                <div class="space-y-4">
                    {{-- Original ticket body as first (user) message --}}
                    <div class="flex justify-end">
                        <div class="max-w-[80%] rounded-2xl bg-cream-200 px-4 py-3">
                            <div class="mb-1 text-xs font-semibold text-ink-500">{{ $ticket->user?->full_name ?? $ticket->email ?? 'المستخدم' }}</div>
                            <p class="whitespace-pre-line text-sm leading-relaxed text-ink-900">{{ $ticket->body }}</p>
                            <div class="mt-1 text-[11px] text-ink-500">{{ optional($ticket->created_at)->format('Y-m-d H:i') }}</div>
                        </div>
                    </div>

                    @foreach ($ticket->replies as $reply)
                        <div class="flex {{ $reply->is_staff ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[80%] rounded-2xl px-4 py-3 {{ $reply->is_staff ? 'bg-coral-soft' : 'bg-cream-200' }}">
                                <div class="mb-1 text-xs font-semibold {{ $reply->is_staff ? 'text-coral' : 'text-ink-500' }}">
                                    {{ $reply->author?->full_name ?? '—' }}{{ $reply->is_staff ? ' · فريق الدعم' : '' }}
                                </div>
                                <p class="whitespace-pre-line text-sm leading-relaxed text-ink-900">{{ $reply->body }}</p>
                                <div class="mt-1 text-[11px] text-ink-500">{{ optional($reply->created_at)->format('Y-m-d H:i') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Reply form --}}
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="mb-3 text-sm font-semibold text-ink-500">إرسال رد</div>
                <form method="POST" action="{{ route('manage.support.reply', $ticket) }}" class="space-y-3">
                    @csrf
                    <textarea name="body" rows="4" required placeholder="اكتب ردك هنا…"
                              class="w-full rounded-xl border border-ink-200 bg-cream-50 px-3 py-2 text-sm outline-none focus:border-coral">{{ old('body') }}</textarea>
                    @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white hover:brightness-95">إرسال الرد</button>
                </form>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="mb-3 text-sm font-semibold text-ink-500">المستخدم</div>
                <div class="font-semibold">{{ $ticket->user?->full_name ?? '—' }}</div>
                <div class="text-sm text-ink-500">{{ $ticket->user?->email ?? $ticket->email }}</div>
            </div>

            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="mb-4 text-sm font-semibold text-ink-500">تغيير الحالة</div>
                <form method="POST" action="{{ route('manage.support.status', $ticket) }}" class="space-y-3">
                    @csrf
                    <select name="status" class="w-full rounded-xl border border-ink-200 bg-cream-50 px-3 py-2.5 text-sm outline-none focus:border-coral">
                        @foreach ($statuses as $case)
                            <option value="{{ $case->value }}" @selected($ticket->status === $case)>{{ $statusLabels[$case->value] ?? $case->value }}</option>
                        @endforeach
                    </select>
                    @error('status')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="w-full rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تحديث الحالة</button>
                </form>
            </div>
        </div>
    </div>
@endsection
