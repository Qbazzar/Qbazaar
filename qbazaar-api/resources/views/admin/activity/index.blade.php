@extends('admin.layout')

@section('title', 'سجل النشاط')
@section('heading', 'سجل النشاط')

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                <x-admin.icon name="search" class="size-[18px]" />
            </span>
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالوصف…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
        </div>

        <select name="log_name" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل السجلات</option>
            @foreach ($logNames as $name)
                <option value="{{ $name }}" @selected($logName === $name)>{{ $name }}</option>
            @endforeach
        </select>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="filter" class="size-[18px]" /> تصفية
        </button>
        @if ($search !== '' || $logName !== '')
            <a href="{{ route('admin.activity.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">السجل</th>
                        <th class="px-4 py-3 font-semibold">الوصف</th>
                        <th class="px-4 py-3 font-semibold">المُنفِّذ</th>
                        <th class="px-4 py-3 font-semibold">الهدف</th>
                        <th class="px-4 py-3 font-semibold">الحدث</th>
                        <th class="px-4 py-3 font-semibold">التاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($activities as $activity)
                        @php
                            $causerName = $activity->causer instanceof \App\Models\User
                                ? $activity->causer->full_name
                                : ($activity->causer_id ? class_basename((string) $activity->causer_type) . ' #' . $activity->causer_id : '—');
                            $subject = $activity->subject_type
                                ? class_basename((string) $activity->subject_type) . ($activity->subject_id ? ' #' . $activity->subject_id : '')
                                : '—';
                        @endphp
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-semibold text-ink-700">{{ $activity->log_name ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ \Illuminate\Support\Str::limit($activity->description, 50) ?: '—' }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $causerName }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-500" dir="ltr">{{ $subject }}</td>
                            <td class="px-4 py-3 text-ink-500">{{ $activity->event ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($activity->created_at)->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-admin.icon name="activity" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا يوجد نشاط مسجّل.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $activities->links() }}
    </div>
@endsection
