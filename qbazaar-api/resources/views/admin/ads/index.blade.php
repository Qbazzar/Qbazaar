@extends('admin.layout')

@section('title', 'الإعلانات')
@section('heading', 'الإعلانات')

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                <x-admin.icon name="search" class="size-[18px]" />
            </span>
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالعنوان أو الرقم…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
        </div>

        <select name="status" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <option value="">كل الحالات</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->value }}</option>
            @endforeach
        </select>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="filter" class="size-[18px]" /> تصفية
        </button>
        @if ($search !== '' || $status !== '')
            <a href="{{ route('admin.ads.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    @include('admin.partials.bulk-bar', ['action' => route('admin.ads.bulk-destroy'), 'label' => 'حذف المحدد', 'confirm' => 'حذف الإعلانات المحددة نهائياً؟'])

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3"><input type="checkbox" id="qb-bulk-all" onclick="qbBulkAll(this)" class="rounded border-ink-300 text-coral"></th>
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
                        <tr class="transition hover:bg-cream-50">
                            <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $ad->id }}" form="qb-bulk-form" class="qb-bulk-cb rounded border-ink-300 text-coral" onchange="qbBulkSync()"></td>
                            <td class="px-4 py-3 text-ink-500">{{ $ad->id }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.ads.show', $ad) }}" class="font-semibold hover:text-coral">{{ \Illuminate\Support\Str::limit($ad->title, 50) }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $ad->user?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3">@include('admin.partials.status-badge', ['status' => $ad->status])</td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($ad->created_at)->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.ads.show', $ad) }}" title="عرض"
                                       class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-coral-soft hover:text-coral">
                                        <x-admin.icon name="eye" class="size-[18px]" />
                                    </a>
                                    <a href="{{ route('admin.ads.edit', $ad) }}" title="تعديل"
                                       class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-coral-soft hover:text-coral">
                                        <x-admin.icon name="pencil" class="size-[18px]" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-admin.icon name="tag" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد إعلانات مطابقة.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $ads->links() }}
    </div>
@endsection
