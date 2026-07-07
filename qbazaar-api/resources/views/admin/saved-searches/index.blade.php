@extends('admin.layout')

@section('title', 'عمليات البحث المحفوظة')
@section('heading', 'عمليات البحث المحفوظة')

@section('content')
    {{-- Filters --}}
    <form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                <x-admin.icon name="search" class="size-[18px]" />
            </span>
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو المستخدم…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
        </div>

        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="filter" class="size-[18px]" /> تصفية
        </button>
        @if ($search !== '')
            <a href="{{ route('admin.saved-searches.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">الاسم</th>
                        <th class="px-4 py-3 font-semibold">المستخدم</th>
                        <th class="px-4 py-3 font-semibold">معايير البحث</th>
                        <th class="px-4 py-3 font-semibold">التاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($savedSearches as $savedSearch)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3 font-semibold">{{ $savedSearch->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $savedSearch->user?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-500" dir="ltr">
                                {{ \Illuminate\Support\Str::limit(is_array($savedSearch->query_params) ? (string) json_encode($savedSearch->query_params, JSON_UNESCAPED_UNICODE) : (string) $savedSearch->query_params, 70) }}
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($savedSearch->created_at)->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-admin.icon name="bookmark" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد عمليات بحث محفوظة.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $savedSearches->links() }}
    </div>
@endsection
