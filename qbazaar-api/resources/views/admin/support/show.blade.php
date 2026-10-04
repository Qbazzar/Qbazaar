@extends('admin.layout')

@section('title', 'تذكرة: ' . $ticket->subject)
@section('heading', 'مراجعة تذكرة')

@section('content')
    <x-admin.back-link :href="route('admin.support.index')" class="mb-4">رجوع للتذاكر</x-admin.back-link>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-admin.card>
                <div class="flex items-start justify-between gap-4">
                    <h2 class="min-w-0 break-words text-xl font-bold">{{ $ticket->subject }}</h2>
                    <x-admin.badge :status="$ticket->status" />
                </div>

                <x-admin.detail-list class="mt-4">
                    <x-admin.detail label="التصنيف">{{ $ticket->category->label()['ar'] }}</x-admin.detail>
                    <x-admin.detail label="الأولوية">{{ $ticket->priority->label()['ar'] }}</x-admin.detail>
                    <x-admin.detail label="المسؤول">{{ $ticket->assignee?->full_name ?? '—' }}</x-admin.detail>
                    <x-admin.detail label="تاريخ الإنشاء">{{ optional($ticket->created_at)->format('Y-m-d H:i') }}</x-admin.detail>
                </x-admin.detail-list>
            </x-admin.card>

            {{-- User messages sit at the inline end, staff replies at the start. --}}
            <x-admin.card title="المحادثة" icon="lifebuoy">
                <div class="space-y-4">
                    <div class="flex justify-end">
                        <div class="max-w-[80%] rounded-2xl bg-cream-200 px-4 py-3">
                            <div class="mb-1 text-xs font-semibold text-ink-500">{{ $ticket->user?->full_name ?? $ticket->email ?? 'المستخدم' }}</div>
                            <p class="whitespace-pre-line break-words text-sm leading-relaxed text-ink-900">{{ $ticket->body }}</p>
                            <div class="mt-1 text-[11px] text-ink-500">{{ optional($ticket->created_at)->format('Y-m-d H:i') }}</div>
                        </div>
                    </div>

                    @foreach ($ticket->replies as $reply)
                        <div class="flex {{ $reply->is_staff ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[80%] rounded-2xl px-4 py-3 {{ $reply->is_staff ? 'bg-coral-soft' : 'bg-cream-200' }}">
                                <div class="mb-1 text-xs font-semibold {{ $reply->is_staff ? 'text-coral-700' : 'text-ink-500' }}">
                                    {{ $reply->author?->full_name ?? '—' }}{{ $reply->is_staff ? ' · فريق الدعم' : '' }}
                                </div>
                                <p class="whitespace-pre-line break-words text-sm leading-relaxed text-ink-900">{{ $reply->body }}</p>
                                <div class="mt-1 text-[11px] text-ink-500">{{ optional($reply->created_at)->format('Y-m-d H:i') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>

            <x-admin.card title="إرسال رد">
                <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" class="space-y-3">
                    @csrf
                    <x-admin.textarea name="body" label="نص الرد" label-hidden :value="old('body')" rows="4" required placeholder="اكتب ردك هنا…" />
                    <x-admin.button icon="chat">إرسال الرد</x-admin.button>
                </form>
            </x-admin.card>
        </div>

        <div class="space-y-6">
            <x-admin.card title="المستخدم">
                <div class="font-semibold">{{ $ticket->user?->full_name ?? '—' }}</div>
                <div class="break-all text-sm text-ink-500">{{ $ticket->user?->email ?? $ticket->email }}</div>
            </x-admin.card>

            <x-admin.card title="تغيير الحالة">
                <form method="POST" action="{{ route('admin.support.status', $ticket) }}" class="space-y-3">
                    @csrf
                    <x-admin.select name="status" label="حالة التذكرة" label-hidden>
                        @foreach ($statuses as $case)
                            <option value="{{ $case->value }}" @selected($ticket->status === $case)>{{ $case->label()['ar'] }}</option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.button variant="secondary" icon="check" block>تحديث الحالة</x-admin.button>
                </form>

                @if ($ticket->assigned_to !== auth()->id())
                    <form method="POST" action="{{ route('admin.support.assign', $ticket) }}" class="mt-3">
                        @csrf
                        <x-admin.button icon="check" block>إسناد التذكرة إليّ</x-admin.button>
                    </form>
                @else
                    <p class="mt-3 text-center text-xs font-semibold text-emerald-700">التذكرة مُسنَدة إليك</p>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
