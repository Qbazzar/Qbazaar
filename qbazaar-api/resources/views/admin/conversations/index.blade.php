@extends('admin.layout')

@section('title', 'المحادثات')
@section('heading', 'المحادثات')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar :reset-url="route('admin.conversations.index')" :search="$search" placeholder="بحث بالإعلان أو المشارك…" />
    </x-admin.page-toolbar>

    <x-admin.table
        caption="المحادثات"
        :columns="['الإعلان', 'المشتري', 'البائع', 'الرسائل', ['label' => 'آخر رسالة', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$conversations->isEmpty()"
        empty-icon="chat"
        empty-message="لا توجد محادثات."
    >
        @foreach ($conversations as $conversation)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.conversations.show', $conversation) }}" class="font-semibold hover:text-coral-700">{{ \Illuminate\Support\Str::limit($conversation->ad?->title ?? '—', 40) }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="المشتري" class="text-ink-700">{{ $conversation->buyer?->full_name ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="البائع" class="text-ink-700">{{ $conversation->seller?->full_name ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="الرسائل"><x-admin.badge>{{ number_format($conversation->messages_count) }}</x-admin.badge></x-admin.table.cell>
                <x-admin.table.cell label="آخر رسالة" secondary class="text-ink-500">{{ optional($conversation->last_message_at)->format('Y-m-d H:i') ?? '—' }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.conversations.show', $conversation)" icon="eye" label="عرض" />
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$conversations" />
@endsection
