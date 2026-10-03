@extends('admin.layout')

@section('title', 'البلاغات')
@section('heading', 'البلاغات')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.reports.index')"
            :search="$search"
            placeholder="بحث بالمُبلِّغ أو الرقم…"
            :filters="[
                ['name' => 'status', 'label' => 'الحالة', 'placeholder' => 'كل الحالات', 'value' => $status,
                    'options' => collect($statuses)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
                ['name' => 'category', 'label' => 'السبب', 'placeholder' => 'كل الأسباب', 'value' => $category,
                    'options' => collect($categories)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
            ]"
        />
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.reports.bulk-dismiss')" icon="x-circle" variant="primary" label="رفض المحدد" confirm="رفض البلاغات المحددة (المعلّقة فقط)؟" />

    <x-admin.table
        caption="البلاغات"
        selectable
        :columns="['الهدف', 'السبب', 'المُبلِّغ', 'الحالة', ['label' => 'التاريخ', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$reports->isEmpty()"
        empty-icon="flag"
        empty-message="لا توجد بلاغات مطابقة."
    >
        @foreach ($reports as $report)
            <x-admin.table.row>
                <x-admin.table.select :value="$report->id" :label="'تحديد البلاغ: ' . $report->category->label()['ar']" />
                <x-admin.table.cell primary>
                    <span class="font-semibold">{{ $report->target_type->label()['ar'] }}</span>
                    <span class="block text-xs text-ink-500">{{ \Illuminate\Support\Str::limit($report->target_id, 12) }}</span>
                </x-admin.table.cell>
                <x-admin.table.cell label="السبب" class="text-ink-700">{{ $report->category->label()['ar'] }}</x-admin.table.cell>
                <x-admin.table.cell label="المُبلِّغ" class="text-ink-700">{{ $report->reporter?->full_name ?? 'الإشراف التلقائي' }}</x-admin.table.cell>
                <x-admin.table.cell label="الحالة"><x-admin.badge :status="$report->status" /></x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" secondary class="text-ink-500">{{ optional($report->created_at)->format('Y-m-d') }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.reports.show', $report)" icon="eye" label="عرض" />
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$reports" />
@endsection
