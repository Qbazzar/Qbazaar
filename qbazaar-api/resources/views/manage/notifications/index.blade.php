@extends('manage.layout')

@section('title', 'الإشعارات')
@section('heading', 'الإشعارات')

@section('content')
    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
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
                        <tr><td colspan="5" class="px-4 py-12 text-center text-ink-500">لا توجد إشعارات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
@endsection
