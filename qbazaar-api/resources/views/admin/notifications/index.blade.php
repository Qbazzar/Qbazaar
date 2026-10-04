@extends('admin.layout')

@section('title', 'الإشعارات')
@section('heading', 'الإشعارات')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.notifications.index')"
            :search="$search"
            placeholder="بحث بالعنوان أو النوع…"
            :filters="[
                ['name' => 'read', 'label' => 'حالة القراءة', 'placeholder' => 'الكل', 'value' => $read,
                    'options' => ['read' => 'مقروءة', 'unread' => 'غير مقروءة']],
            ]"
        />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="الإشعارات"
        :columns="['النوع', 'المستلم', 'المحتوى', 'الحالة', ['label' => 'التاريخ', 'secondary' => true]]"
        :empty="$notifications->isEmpty()"
        empty-icon="bell"
        empty-message="لا توجد إشعارات."
    >
        @foreach ($notifications as $notification)
            @php
                $data = is_array($notification->data) ? $notification->data : [];
                $summary = $data['title'] ?? $data['body'] ?? '';
                $notifiable = is_a($notification->notifiable_type, \App\Models\User::class, true)
                    ? ($userNames[$notification->notifiable_id] ?? $notification->notifiable_id)
                    : class_basename($notification->notifiable_type) . ' #' . $notification->notifiable_id;
            @endphp
            <x-admin.table.row>
                <x-admin.table.cell primary><x-admin.badge>{{ class_basename($notification->type) }}</x-admin.badge></x-admin.table.cell>
                <x-admin.table.cell label="المستلم" class="text-ink-700">{{ $notifiable }}</x-admin.table.cell>
                <x-admin.table.cell label="المحتوى" class="text-ink-500">{{ \Illuminate\Support\Str::limit($summary, 60) ?: '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="الحالة">
                    <x-admin.badge :tone="$notification->read_at ? 'success' : 'warning'">{{ $notification->read_at ? 'مقروء' : 'غير مقروء' }}</x-admin.badge>
                </x-admin.table.cell>
                <x-admin.table.cell label="التاريخ" secondary class="text-ink-500">{{ optional($notification->created_at)->format('Y-m-d H:i') }}</x-admin.table.cell>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$notifications" />
@endsection
