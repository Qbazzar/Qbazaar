@extends('manage.layout')

@section('title', 'محادثة')
@section('heading', 'عرض المحادثة')

@section('content')
    <a href="{{ route('manage.conversations.index') }}" class="mb-4 inline-block text-sm font-semibold text-ink-500 hover:text-coral">→ رجوع</a>

    {{-- Meta --}}
    <div class="mb-6 rounded-2xl border border-ink-200 bg-cream-100 p-6">
        <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
            <div><span class="text-ink-500">الإعلان:</span> <span class="font-semibold">{{ $conversation->ad?->title ?? '—' }}</span></div>
            <div><span class="text-ink-500">المشتري:</span> {{ $conversation->buyer?->full_name ?? '—' }}</div>
            <div><span class="text-ink-500">البائع:</span> {{ $conversation->seller?->full_name ?? '—' }}</div>
            <div><span class="text-ink-500">عدد الرسائل:</span> {{ number_format($messages->count()) }}</div>
        </div>
    </div>

    {{-- Thread --}}
    <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
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
                            <div class="rounded-2xl bg-cream-200 px-4 py-2.5 text-center text-xs font-medium text-ink-500">{{ $message->body }}</div>
                        @else
                            <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed {{ $fromSeller ? 'bg-cream-200 text-ink-900' : 'bg-coral text-white' }}">
                                @if ($message->type === \App\Enums\MessageType::OFFER)
                                    <span class="mb-1 block text-xs font-bold opacity-80">عرض سعر</span>
                                @endif
                                <p class="whitespace-pre-line">{{ $message->body }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-ink-500">لا توجد رسائل في هذه المحادثة.</p>
            @endforelse
        </div>
    </div>
@endsection
