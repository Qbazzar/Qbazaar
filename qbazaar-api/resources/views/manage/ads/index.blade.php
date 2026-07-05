@extends('manage.layout')

@section('title', 'الإعلانات')
@section('heading', 'الإعلانات')

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالعنوان أو الرقم…"
               class="w-64 rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">

        <select name="status" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل الحالات</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->value }}</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تصفية</button>
        @if ($search !== '' || $status !== '')
            <a href="{{ route('manage.ads.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">#</th>
                        <th class="px-4 py-3 font-semibold">العنوان</th>
                        <th class="px-4 py-3 font-semibold">البائع</th>
                        <th class="px-4 py-3 font-semibold">الحالة</th>
                        <th class="px-4 py-3 font-semibold">التاريخ</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($ads as $ad)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3 text-ink-500">{{ $ad->id }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('manage.ads.show', $ad) }}" class="font-semibold hover:text-coral">{{ \Illuminate\Support\Str::limit($ad->title, 50) }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $ad->user?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3">@include('manage.partials.status-badge', ['status' => $ad->status])</td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($ad->created_at)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-left">
                                <a href="{{ route('manage.ads.show', $ad) }}" class="rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">عرض</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-ink-500">لا توجد إعلانات مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $ads->links() }}
    </div>
@endsection
