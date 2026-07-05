@extends('manage.layout')

@section('title', 'بلاغ')
@section('heading', 'مراجعة بلاغ')

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
    $isPending = $report->status === \App\Enums\ReportStatus::PENDING;
@endphp

@section('content')
    <a href="{{ url()->previous() }}" class="mb-4 inline-block text-sm font-semibold text-ink-500 hover:text-coral">→ رجوع</a>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Details --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-bold">{{ $report->category->label()['ar'] }}</h2>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusStyles[$report->status->value] ?? 'bg-cream-200 text-ink-500' }}">
                        {{ $statusLabels[$report->status->value] ?? $report->status->value }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div><span class="text-ink-500">نوع الهدف:</span> {{ $targetLabels[$report->target_type->value] ?? $report->target_type->value }}</div>
                    <div>
                        <span class="text-ink-500">مُعرّف الهدف:</span>
                        @if ($report->target_type === \App\Enums\ReportTarget::AD)
                            <a href="{{ route('manage.ads.show', $report->target_id) }}" class="font-semibold hover:text-coral">{{ $report->target_id }}</a>
                        @else
                            <span class="font-mono">{{ $report->target_id }}</span>
                        @endif
                    </div>
                    <div><span class="text-ink-500">تاريخ الإنشاء:</span> {{ optional($report->created_at)->format('Y-m-d H:i') }}</div>
                    <div><span class="text-ink-500">تاريخ المراجعة:</span> {{ optional($report->reviewed_at)->format('Y-m-d H:i') ?? '—' }}</div>
                </div>

                <div class="mt-5">
                    <div class="mb-1.5 text-sm font-semibold text-ink-500">وصف البلاغ</div>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-ink-700">{{ $report->description ?: '—' }}</p>
                </div>

                @if ($report->admin_notes)
                    <div class="mt-5">
                        <div class="mb-1.5 text-sm font-semibold text-ink-500">ملاحظات الإدارة</div>
                        <p class="whitespace-pre-line text-sm leading-relaxed text-ink-700">{{ $report->admin_notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="space-y-6">
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <div class="mb-3 text-sm font-semibold text-ink-500">المُبلِّغ</div>
                <div class="font-semibold">{{ $report->reporter?->full_name ?? '—' }}</div>
                <div class="text-sm text-ink-500">{{ $report->reporter?->email }}</div>
            </div>

            @if ($report->reviewer)
                <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                    <div class="mb-3 text-sm font-semibold text-ink-500">راجعه</div>
                    <div class="font-semibold">{{ $report->reviewer->full_name }}</div>
                </div>
            @endif

            @if ($isPending)
                <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                    <div class="mb-4 text-sm font-semibold text-ink-500">إجراءات الإشراف</div>
                    <div class="space-y-3">
                        <form method="POST" action="{{ route('manage.reports.resolve', $report) }}">
                            @csrf
                            <button class="w-full rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تعليم كمراجَع</button>
                        </form>

                        <form method="POST" action="{{ route('manage.reports.dismiss', $report) }}">
                            @csrf
                            <button class="w-full rounded-xl border border-ink-200 px-4 py-2.5 text-sm font-semibold text-ink-700 hover:bg-cream-200">رفض البلاغ</button>
                        </form>

                        <form method="POST" action="{{ route('manage.reports.action', $report) }}" class="space-y-2">
                            @csrf
                            <textarea name="admin_notes" rows="3" required placeholder="ملاحظات الإجراء المتخذ…"
                                      class="w-full rounded-xl border border-ink-200 bg-cream-50 px-3 py-2 text-sm outline-none focus:border-coral">{{ old('admin_notes') }}</textarea>
                            @error('admin_notes')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            <button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تم اتخاذ إجراء</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
