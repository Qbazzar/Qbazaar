@extends('admin.layout')

@section('title', 'بلاغ')
@section('heading', 'مراجعة بلاغ')

@php($isPending = $report->status === \App\Enums\ReportStatus::PENDING)

@section('content')
    <x-admin.back-link :href="route('admin.reports.index')" class="mb-4">رجوع للبلاغات</x-admin.back-link>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.card>
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-xl font-bold">{{ $report->category->label()['ar'] }}</h2>
                    <x-admin.badge :status="$report->status" />
                </div>

                <x-admin.detail-list class="mt-4">
                    <x-admin.detail label="نوع الهدف">{{ $report->target_type->label()['ar'] }}</x-admin.detail>
                    <x-admin.detail label="مُعرّف الهدف">
                        @if ($report->target_type === \App\Enums\ReportTarget::AD)
                            <a href="{{ route('admin.ads.show', $report->target_id) }}" class="font-semibold hover:text-coral-700">{{ $report->target_id }}</a>
                        @else
                            <span class="font-mono">{{ $report->target_id }}</span>
                        @endif
                    </x-admin.detail>
                    <x-admin.detail label="تاريخ الإنشاء">{{ optional($report->created_at)->format('Y-m-d H:i') }}</x-admin.detail>
                    <x-admin.detail label="تاريخ المراجعة">{{ optional($report->reviewed_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.detail>
                </x-admin.detail-list>

                <div class="mt-5">
                    <h3 class="mb-1.5 text-sm font-semibold text-ink-500">وصف البلاغ</h3>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-ink-700">{{ $report->description ?: '—' }}</p>
                </div>

                @if ($report->admin_notes)
                    <div class="mt-5">
                        <h3 class="mb-1.5 text-sm font-semibold text-ink-500">ملاحظات الإدارة</h3>
                        <p class="whitespace-pre-line text-sm leading-relaxed text-ink-700">{{ $report->admin_notes }}</p>
                    </div>
                @endif
            </x-admin.card>
        </div>

        <div class="space-y-6">
            <x-admin.card title="المُبلِّغ">
                <div class="font-semibold">{{ $report->reporter?->full_name ?? 'الإشراف التلقائي' }}</div>
                <div class="break-all text-sm text-ink-500">{{ $report->reporter?->email }}</div>
            </x-admin.card>

            @if ($report->reviewer)
                <x-admin.card title="راجعه">
                    <div class="font-semibold">{{ $report->reviewer->full_name }}</div>
                </x-admin.card>
            @endif

            @if ($isPending)
                <x-admin.card title="إجراءات الإشراف">
                    <div class="space-y-3">
                        <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
                            @csrf
                            <x-admin.button variant="info" icon="check" block>تعليم كمراجَع</x-admin.button>
                        </form>

                        <form method="POST" action="{{ route('admin.reports.dismiss', $report) }}">
                            @csrf
                            <x-admin.button variant="secondary" icon="x-circle" block>رفض البلاغ</x-admin.button>
                        </form>

                        <form method="POST" action="{{ route('admin.reports.action', $report) }}" class="space-y-2">
                            @csrf
                            <x-admin.textarea name="admin_notes" label="ملاحظات الإجراء" label-hidden :value="old('admin_notes')" rows="3" required placeholder="ملاحظات الإجراء المتخذ…" />
                            <x-admin.button variant="success" icon="check" block>تم اتخاذ إجراء</x-admin.button>
                        </form>

                        @if ($report->target_type === \App\Enums\ReportTarget::AD)
                            <form method="POST" action="{{ route('admin.reports.suspend-ad', $report) }}" data-confirm="إيقاف الإعلان المُبلَّغ عنه وإغلاق البلاغ؟">
                                @csrf
                                <x-admin.button variant="danger" icon="ban" block>إيقاف الإعلان المُبلَّغ عنه</x-admin.button>
                            </form>
                        @endif

                        @if ($report->target_type === \App\Enums\ReportTarget::USER)
                            <form method="POST" action="{{ route('admin.reports.ban-user', $report) }}" data-confirm="إيقاف المستخدم المُبلَّغ عنه وإغلاق البلاغ؟">
                                @csrf
                                <x-admin.button variant="danger" icon="ban" block>إيقاف المستخدم المُبلَّغ عنه</x-admin.button>
                            </form>
                        @endif
                    </div>
                </x-admin.card>
            @endif
        </div>
    </div>
@endsection
