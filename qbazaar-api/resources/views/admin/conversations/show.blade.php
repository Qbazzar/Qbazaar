@extends('admin.layout')

@section('title', 'محادثة')
@section('heading', 'عرض المحادثة')

@section('content')
    <x-admin.back-link :href="route('admin.conversations.index')" class="mb-4">رجوع للمحادثات</x-admin.back-link>

    <x-admin.card class="mb-6">
        <x-admin.detail-list class="md:grid-cols-4">
            <x-admin.detail label="الإعلان"><span class="font-semibold">{{ $conversation->ad?->title ?? '—' }}</span></x-admin.detail>
            <x-admin.detail label="المشتري">{{ $conversation->buyer?->full_name ?? '—' }}</x-admin.detail>
            <x-admin.detail label="البائع">{{ $conversation->seller?->full_name ?? '—' }}</x-admin.detail>
            <x-admin.detail label="عدد الرسائل">{{ number_format($messages->count()) }}</x-admin.detail>
        </x-admin.detail-list>
    </x-admin.card>

    <x-admin.card title="المحادثة" icon="chat">
        <div class="space-y-4">
            @php($sellerId = $conversation->seller_id)
            @forelse ($messages as $message)
                @php($fromSeller = $message->sender_id === $sellerId)
                <div class="flex {{ $fromSeller ? 'justify-start' : 'justify-end' }}">
                    <div class="max-w-lg">
                        <div class="mb-1 flex items-center gap-2 text-xs text-ink-500 {{ $fromSeller ? '' : 'flex-row-reverse' }}">
                            <span class="font-semibold text-ink-700">{{ $message->sender?->full_name ?? '—' }}</span>
                            <span>{{ optional($message->created_at)->format('Y-m-d H:i') }}</span>
                        </div>
                        @if ($message->type === \App\Enums\MessageType::SYSTEM)
                            <div class="rounded-2xl bg-cream-200 px-4 py-2.5 text-center text-xs font-medium text-ink-700">{{ $message->body }}</div>
                        @else
                            <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed {{ $fromSeller ? 'bg-cream-200 text-ink-900' : 'bg-coral-600 text-white' }}">
                                @if ($message->type === \App\Enums\MessageType::OFFER)
                                    <span class="mb-1 block text-xs font-bold">عرض سعر</span>
                                @endif
                                <p class="whitespace-pre-line break-words">{{ $message->body }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-ink-500">لا توجد رسائل في هذه المحادثة.</p>
            @endforelse
        </div>
    </x-admin.card>
@endsection
