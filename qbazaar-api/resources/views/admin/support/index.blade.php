@extends('admin.layout')

@section('title', 'الدعم الفني')
@section('heading', 'الدعم الفني')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.support.index')"
            :search="$search"
            placeholder="بحث بالموضوع أو المستخدم…"
            :filters="[
                ['name' => 'status', 'label' => 'الحالة', 'placeholder' => 'كل الحالات', 'value' => $status,
                    'options' => collect($statuses)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
                ['name' => 'priority', 'label' => 'الأولوية', 'placeholder' => 'كل الأولويات', 'value' => $priority,
                    'options' => collect($priorities)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
            ]"
        />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="تذاكر الدعم"
        :columns="['الموضوع', 'المستخدم', 'الحالة', 'الأولوية', ['label' => 'الردود', 'secondary' => true], ['label' => 'آخر رد', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$tickets->isEmpty()"
        empty-icon="lifebuoy"
        empty-message="لا توجد تذاكر مطابقة."
    >
        @foreach ($tickets as $ticket)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.support.show', $ticket) }}" class="font-semibold hover:text-coral-700">{{ \Illuminate\Support\Str::limit($ticket->subject, 60) }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="المستخدم" class="text-ink-700">{{ $ticket->user?->full_name ?? $ticket->email ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="الحالة"><x-admin.badge :status="$ticket->status" /></x-admin.table.cell>
                <x-admin.table.cell label="الأولوية"><x-admin.badge :status="$ticket->priority" /></x-admin.table.cell>
                <x-admin.table.cell label="الردود" secondary class="text-ink-500">{{ $ticket->replies_count }}</x-admin.table.cell>
                <x-admin.table.cell label="آخر رد" secondary class="text-ink-500">{{ $ticket->last_replied_at?->format('Y-m-d H:i') ?? '—' }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.support.show', $ticket)" icon="eye" label="عرض" />
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$tickets" />
@endsection
