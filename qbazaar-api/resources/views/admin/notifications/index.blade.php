@extends('admin.layout')

@section('title', 'الإشعارات')
@section('heading', 'الإشعارات')

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                <x-admin.icon name="search" class="size-[18px]" />
            </span>
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالعنوان أو النوع…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
        </div>

        <select name="read" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">الكل</option>
            <option value="read" @selected($read === 'read')>مقروءة</option>
            <option value="unread" @selected($read === 'unread')>غير مقروءة</option>
        </select>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="filter" class="size-[18px]" /> تصفية
        </button>
        @if ($search !== '' || $read !== '')
            <a href="{{ route('admin.notifications.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">النوع</th>
                        <th class="px-4 py-3 font-semibold">المستلم</th>
                        <th class="px-4 py-3 font-semibold">المحتوى</th>
                        <th class="px-4 py-3 font-semibold">الحالة</th>
                        <th class="px-4 py-3 font-semibold">التاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($notifications as $notification)
                        @php
                            $data = is_array($notification->data) ? $notification->data : [];
                            $summary = $data['title'] ?? $data['body'] ?? '';
                            $notifiable = is_a($notification->notifiable_type, \App\Models\User::class, true)
                                ? ($userNames[$notification->notifiable_id] ?? $notification->notifiable_id)
                                : class_basename($notification->notifiable_type) . ' #' . $notification->notifiable_id;
                        @endphp
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-semibold text-ink-700">{{ class_basename($notification->type) }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $notifiable }}</td>
                            <td class="px-4 py-3 text-ink-500">{{ \Illuminate\Support\Str::limit($summary, 60) ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($notification->read_at)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">مقروء</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">غير مقروء</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($notification->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-admin.icon name="bell" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد إشعارات.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
@endsection
