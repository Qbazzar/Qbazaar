@extends('admin.layout')

@section('title', 'المحادثات')
@section('heading', 'المحادثات')

@section('content')
    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">الإعلان</th>
                        <th class="px-4 py-3 font-semibold">المشتري</th>
                        <th class="px-4 py-3 font-semibold">البائع</th>
                        <th class="px-4 py-3 font-semibold">الرسائل</th>
                        <th class="px-4 py-3 font-semibold">آخر رسالة</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($conversations as $conversation)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.conversations.show', $conversation) }}" class="font-semibold hover:text-coral">{{ \Illuminate\Support\Str::limit($conversation->ad?->title ?? '—', 40) }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $conversation->buyer?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $conversation->seller?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-semibold text-ink-700">{{ number_format($conversation->messages_count) }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($conversation->last_message_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.conversations.show', $conversation) }}" title="عرض"
                                       class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-coral-soft hover:text-coral">
                                        <x-admin.icon name="eye" class="size-[18px]" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-admin.icon name="chat" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد محادثات.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $conversations->links() }}
    </div>
@endsection
