@extends('admin.layout')

@section('title', 'سجل النشاط')
@section('heading', 'سجل النشاط')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.activity.index')"
            :search="$search"
            placeholder="بحث بالوصف…"
            :filters="[
                ['name' => 'log_name', 'label' => 'السجل', 'placeholder' => 'كل السجلات', 'value' => $logName,
                    'options' => collect($logNames)->mapWithKeys(fn ($name) => [$name => $name])],
            ]"
        />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="سجل النشاط"
        :columns="['السجل', 'الوصف', 'المُنفِّذ', ['label' => 'الهدف', 'secondary' => true], ['label' => 'الحدث', 'secondary' => true], 'التاريخ']"
        :empty="$activities->isEmpty()"
        empty-icon="activity"
        empty-message="لا يوجد نشاط مسجّل."
    >
        @foreach ($activities as $activity)
            @php
                $causerName = $activity->causer instanceof \App\Models\User
                    ? $activity->causer->full_name
                    : ($activity->causer_id ? class_basename((string) $activity->causer_type) . ' #' . $activity->causer_id : '—');
                $subject = $activity->subject_type
                    ? class_basename((string) $activity->subject_type) . ($activity->subject_id ? ' #' . $activity->subject_id : '')
                    : '—';
            @endphp
            <x-admin.table.row>
                <x-admin.table.cell primary><x-admin.badge>{{ $activity->log_name ?? '—' }}</x-admin.badge></x-admin.table.cell>
                <x-admin.table.cell label="الوصف" class="text-ink-700">{{ \Illuminate\Support\Str::limit($activity->description, 50) ?: '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="المُنفِّذ" class="text-ink-700">{{ $causerName }}</x-admin.table.cell>
                <x-admin.table.cell label="الهدف" secondary class="font-mono text-xs text-ink-500"><span dir="ltr">{{ $subject }}</span></x-admin.table.cell>
                <x-admin.table.cell label="الحدث" secondary class="text-ink-500">{{ $activity->event ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" class="text-ink-500">{{ optional($activity->created_at)->format('Y-m-d H:i') }}</x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$activities" />
@endsection
