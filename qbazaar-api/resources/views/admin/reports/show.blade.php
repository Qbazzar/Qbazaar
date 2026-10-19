@extends('admin.layout')

@section('title', 'بلاغ #' . $report->id)
@section('heading', 'مراجعة بلاغ')

@use('App\Enums\ReportTarget')

@php
    $staff = auth()->user();
    $isPending = $report->status === \App\Enums\ReportStatus::PENDING;
    $targetType = $report->target_type;
    $dateTime = fn ($value): string => $value?->format('Y-m-d H:i') ?? '—';
    $isTrashed = fn ($model): bool => method_exists($model, 'trashed') && $model->trashed();
    // Where the reported record opens in the panel, when it still exists and this staff member may open it.
    $targetUrl = match (true) {
        ! $target instanceof \Illuminate\Database\Eloquent\Model, $isTrashed($target) => null,
        $targetType === ReportTarget::AD && $staff->can('ads.view') => route('admin.ads.show', $target),
        $targetType === ReportTarget::USER && $staff->can('users.view') => route('admin.users.show', $target),
        $targetType === ReportTarget::CONVERSATION && $staff->can('conversations.view') => route('admin.conversations.show', $target),
        $targetType === ReportTarget::MESSAGE && $staff->can('conversations.view') => route('admin.conversations.show', $target->conversation_id),
        default => null,
    };
    $targetLinkLabel = match ($targetType) {
        ReportTarget::AD => 'فتح الإعلان',
        ReportTarget::USER => 'فتح الملف',
        default => 'فتح المحادثة',
    };
    $canAct = $isPending && $staff->can('reports.action');
@endphp

@section('content')
    <x-admin.page :title="$report->category->label()['ar']"
                  :crumbs="[['البلاغات', route('admin.reports.index')], 'بلاغ #' . $report->id]"
                  :subtitle="'بلاغ على ' . $targetType->label()['ar'] . ' · قُدّم في ' . $dateTime($report->created_at)"
                  side-label="المُبلِّغ وإجراءات البلاغ">
        <x-slot:badges>
            <x-admin.badge :status="$report->status" />
            <x-admin.badge>{{ $targetType->label()['ar'] }}</x-admin.badge>
        </x-slot:badges>

        <x-slot:actions>
            @if ($targetUrl)
                <x-admin.button variant="secondary" :href="$targetUrl" icon="eye">{{ $targetLinkLabel }}</x-admin.button>
            @endif
        </x-slot:actions>

        @if ($errors->any())
            <x-slot:lead>
                <x-admin.alert tone="danger" title="تعذّر تنفيذ الإجراء. راجع ما يلي:" :summary="$errors" autofocus />
            </x-slot:lead>
        @endif

        {{-- Main column --}}
        <x-admin.form-section id="report-details" title="تفاصيل البلاغ" icon="flag">
            <dl class="grid gap-x-6 gap-y-4 @min-[34rem]:grid-cols-2 @min-[46rem]:grid-cols-4">
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">نوع الهدف</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-900">{{ $targetType->label()['ar'] }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">مُعرّف الهدف</dt>
                    <dd class="mt-0.5 text-sm font-semibold break-all text-ink-900">
                        @if ($targetUrl && $targetType === ReportTarget::AD)
                            <a href="{{ $targetUrl }}" dir="ltr" class="font-mono text-xs hover:text-coral-700">{{ $report->target_id }}</a>
                        @else
                            <span dir="ltr" class="font-mono text-xs select-all">{{ $report->target_id }}</span>
                        @endif
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">تاريخ الإنشاء</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-900"><span dir="ltr">{{ $dateTime($report->created_at) }}</span></dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs text-ink-500">تاريخ المراجعة</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-ink-900"><span dir="ltr">{{ $dateTime($report->reviewed_at) }}</span></dd>
                </div>
            </dl>

            <div>
                <h3 class="mb-1.5 text-[13px] font-semibold text-ink-500">وصف البلاغ</h3>
                <p class="max-w-[70ch] text-sm leading-[1.9] whitespace-pre-line break-words text-ink-700">{{ $report->description ?: '—' }}</p>
            </div>

            @if ($report->admin_notes)
                <div>
                    <h3 class="mb-1.5 text-[13px] font-semibold text-ink-500">ملاحظات الإدارة</h3>
                    <p class="max-w-[70ch] text-sm leading-[1.9] whitespace-pre-line break-words text-ink-700">{{ $report->admin_notes }}</p>
                </div>
            @endif
        </x-admin.form-section>

        <x-admin.form-section id="report-target" title="العنصر المُبلَّغ عنه" icon="eye">
            @if ($target === false)
                <x-admin.error-state :retry-url="request()->fullUrl()" />
            @elseif ($target === null)
                <x-admin.empty-state compact icon="inbox" message="العنصر المُبلَّغ عنه لم يعد موجوداً." />
            @elseif ($targetType === ReportTarget::AD)
                <div class="flex items-start gap-4">
                    <x-admin.ad-thumb :ad="$target" />
                    <div class="grid min-w-0 gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($targetUrl)
                                <a href="{{ $targetUrl }}" class="font-bold break-words text-ink-900 hover:text-coral-700">{{ $target->title }}</a>
                            @else
                                <span class="font-bold break-words text-ink-900">{{ $target->title }}</span>
                            @endif
                            <x-admin.badge :status="$target->status" />
                            @if ($isTrashed($target))
                                <x-admin.badge tone="danger">محذوف</x-admin.badge>
                            @endif
                        </div>
                        <p class="text-[13px] text-ink-500">
                            <span dir="ltr">{{ $target->price !== null ? number_format((float) $target->price, 2) . ' ر.ق' : '—' }}</span>
                            @if ($target->category)
                                <span aria-hidden="true"> · </span>{{ $target->category->getLocalizedName(app()->getLocale()) }}
                            @endif
                        </p>
                        <p class="text-[13px] text-ink-500">البائع: <x-admin.user :user="$target->user" size="xs" fallback="حساب محذوف" /></p>
                    </div>
                </div>
            @elseif ($targetType === ReportTarget::USER)
                <div class="flex items-start gap-4">
                    <x-admin.user-avatar :user="$target" size="lg" decorative />
                    <div class="grid min-w-0 flex-1 gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($targetUrl)
                                <a href="{{ $targetUrl }}" class="font-bold text-ink-900 hover:text-coral-700">{{ $target->full_name ?: $target->email }}</a>
                            @else
                                <span class="font-bold text-ink-900">{{ $target->full_name ?: $target->email }}</span>
                            @endif
                            <x-admin.badge :status="$target->status" />
                            @if ($isTrashed($target))
                                <x-admin.badge tone="danger">محذوف</x-admin.badge>
                            @endif
                        </div>
                        <x-admin.meta-list class="max-w-md">
                            <x-admin.meta-item label="البريد" ltr>{{ $target->email }}</x-admin.meta-item>
                            @if ($target->phone)
                                <x-admin.meta-item label="الهاتف"><x-admin.phone :phone="$target->phone" /></x-admin.meta-item>
                            @endif
                            <x-admin.meta-item label="عضو منذ" ltr>{{ $target->created_at->format('Y-m-d') }}</x-admin.meta-item>
                        </x-admin.meta-list>
                    </div>
                </div>
            @elseif ($targetType === ReportTarget::CONVERSATION)
                <x-admin.meta-list class="max-w-xl">
                    <x-admin.meta-item label="الإعلان">{{ $target->ad?->title ?? 'إعلان محذوف' }}</x-admin.meta-item>
                    <x-admin.meta-item label="المشتري"><x-admin.user :user="$target->buyer" size="xs" fallback="حساب محذوف" /></x-admin.meta-item>
                    <x-admin.meta-item label="البائع"><x-admin.user :user="$target->seller" size="xs" fallback="حساب محذوف" /></x-admin.meta-item>
                    <x-admin.meta-item label="آخر رسالة" ltr>{{ $dateTime($target->last_message_at) }}</x-admin.meta-item>
                </x-admin.meta-list>
            @elseif (! $canReadMessage)
                <x-admin.empty-state compact icon="inbox" message="نص الرسالة المُبلَّغ عنها مقيّد، ويظهر لمن يملك صلاحية اتخاذ إجراء على البلاغات." />
            @else
                <figure class="grid gap-2">
                    <blockquote class="max-w-[70ch] rounded-xl border border-ink-200 bg-cream-50 px-4 py-3 text-sm leading-[1.9] whitespace-pre-line break-words text-ink-700">{{ \Illuminate\Support\Str::limit((string) $target->body, \App\Services\Admin\Reports\ReportDetailPanels::MESSAGE_PREVIEW_LENGTH) ?: '—' }}</blockquote>
                    <figcaption class="flex flex-wrap items-center gap-x-2 text-[13px] text-ink-500">
                        <x-admin.user :user="$target->sender" size="xs" fallback="حساب محذوف" />
                        <span aria-hidden="true">·</span>
                        <span dir="ltr">{{ $dateTime($target->created_at) }}</span>
                    </figcaption>
                </figure>
            @endif
        </x-admin.form-section>

        <section aria-labelledby="other-reports-title" class="grid gap-3">
            <div class="flex flex-wrap items-end justify-between gap-3 px-1">
                <div class="min-w-0">
                    <h2 id="other-reports-title" class="text-[15px] font-bold text-ink-900">بلاغات أخرى على العنصر نفسه</h2>
                    @if ($otherReports?->total)
                        <p class="mt-1 text-[13px] text-ink-500">الأحدث أولاً، {{ $otherReports->latest->count() }} من {{ number_format($otherReports->total) }}، منها {{ $otherReports->open }} بانتظار المراجعة</p>
                    @endif
                </div>
                @if ($otherReports?->hasMore())
                    <a href="{{ route('admin.reports.index', ['q' => $report->target_id]) }}"
                       class="shrink-0 rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-semibold text-ink-700 transition hover:bg-coral-soft hover:text-coral-700">عرض الكل ({{ number_format($otherReports->total) }})</a>
                @endif
            </div>

            @if ($otherReports === null)
                <x-admin.card><x-admin.error-state :retry-url="request()->fullUrl()" /></x-admin.card>
            @else
                <x-admin.table caption="بلاغات أخرى على العنصر نفسه" :columns="['التصنيف', 'المُبلِّغ', 'الحالة', 'التاريخ']"
                               :empty="$otherReports->latest->isEmpty()" empty-icon="flag" empty-message="لا توجد بلاغات أخرى على هذا العنصر.">
                    @foreach ($otherReports->latest as $other)
                        <x-admin.table.row>
                            <x-admin.table.cell primary>
                                <a href="{{ route('admin.reports.show', $other) }}" class="font-bold hover:text-coral-700">{{ $other->category->label()['ar'] }}</a>
                            </x-admin.table.cell>
                            <x-admin.table.cell label="المُبلِّغ"><x-admin.user :user="$other->reporter" size="xs" :fallback="__('admin.report.automatic_reporter')" /></x-admin.table.cell>
                            <x-admin.table.cell label="الحالة"><x-admin.badge :status="$other->status" /></x-admin.table.cell>
                            <x-admin.table.cell label="التاريخ" class="text-ink-500"><span dir="ltr">{{ $other->created_at->format('Y-m-d') }}</span></x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            @endif
        </section>

        {{-- Side column --}}
        <x-slot:side>
            @if ($canAct)
                <x-admin.side-card id="report-moderation" title="إجراءات الإشراف" icon="shield">
                    <div class="grid gap-2.5">
                        <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
                            @csrf
                            <x-admin.button variant="info" icon="check" block>تعليم كمراجَع</x-admin.button>
                        </form>

                        <form method="POST" action="{{ route('admin.reports.dismiss', $report) }}">
                            @csrf
                            <x-admin.button variant="secondary" icon="x-circle" block>رفض البلاغ</x-admin.button>
                        </form>

                        <form method="POST" action="{{ route('admin.reports.action', $report) }}" class="grid gap-2">
                            @csrf
                            <x-admin.textarea name="admin_notes" label="ملاحظات الإجراء" label-hidden :value="old('admin_notes')" rows="3" required placeholder="ملاحظات الإجراء المتخذ…" />
                            <x-admin.button variant="success" icon="check" block>تم اتخاذ إجراء</x-admin.button>
                        </form>

                        {{-- One button: the chosen sanction runs and the report closes as actioned in the same step. --}}
                        @if ($sanctionActions !== [])
                            <x-admin.sanction-form id="report-sanction" prefix="report-sanction" :action="route('admin.reports.sanction', $report)"
                                                   :label="__('admin.moderation.report.title')" :submit="__('admin.moderation.report.submit')"
                                                   :confirm="__('admin.moderation.report.confirm')" :intro="__('admin.moderation.report.intro')">
                                <fieldset class="grid gap-2">
                                    <legend class="mb-1.5 text-sm font-semibold text-ink-700">{{ __('admin.moderation.fields.action') }}<span class="ms-0.5 text-danger-700" aria-hidden="true">*</span></legend>
                                    @foreach ($sanctionActions as $sanctionAction)
                                        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-ink-200 bg-cream-50 px-3 py-2 transition has-checked:border-coral-600 has-checked:bg-coral-soft">
                                            <input type="radio" name="action" value="{{ $sanctionAction->value }}" required
                                                   @checked(old('action') === $sanctionAction->value || (count($sanctionActions) === 1 && old('action') === null))
                                                   class="mt-1 size-4 shrink-0 accent-coral-600">
                                            <span class="grid gap-0.5">
                                                <span class="text-sm font-semibold text-ink-900">{{ $sanctionAction->label() }}</span>
                                                <span class="text-xs text-ink-500">{{ __('admin.moderation.report.targets.' . $sanctionAction->value) }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                    @error('action')
                                        <p class="text-xs font-semibold text-danger-700">{{ $message }}</p>
                                    @enderror
                                </fieldset>
                            </x-admin.sanction-form>
                        @elseif ($targetType !== ReportTarget::CONVERSATION)
                            <p class="text-xs leading-relaxed text-ink-500">{{ __('admin.moderation.report.none') }}</p>
                        @endif
                    </div>
                </x-admin.side-card>
            @endif

            <x-admin.side-card id="report-reporter" title="المُبلِّغ" icon="users"
                               :link="$report->reporter && $staff->can('users.view') ? ['فتح الملف', route('admin.users.show', $report->reporter)] : null">
                @if ($report->reporter)
                    <div class="flex items-center gap-3">
                        <x-admin.user-avatar :user="$report->reporter" size="md" decorative />
                        <div class="min-w-0">
                            <p class="truncate font-bold text-ink-900">{{ $report->reporter->full_name ?: $report->reporter->email }}</p>
                            <p class="truncate text-[13px] text-ink-500" dir="ltr">{{ $report->reporter->email }}</p>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-3">
                        <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-cream-200 text-ink-500"><x-admin.icon name="shield" class="size-5" /></span>
                        <p class="font-bold text-ink-900">{{ __('admin.report.automatic_reporter') }}</p>
                    </div>
                @endif
            </x-admin.side-card>

            @if ($report->reviewer)
                <x-admin.side-card id="report-reviewer" title="راجعه" icon="check">
                    <x-admin.user :user="$report->reviewer" size="md" />
                    <x-admin.meta-list class="mt-4">
                        <x-admin.meta-item label="تاريخ المراجعة" ltr>{{ $dateTime($report->reviewed_at) }}</x-admin.meta-item>
                    </x-admin.meta-list>
                </x-admin.side-card>
            @endif

            <x-admin.side-card id="report-history" title="سجل البلاغ" icon="activity">
                @if ($timeline === null)
                    <x-admin.error-state :retry-url="request()->fullUrl()" />
                @else
                    <x-admin.timeline :entries="$timeline" />
                @endif
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
