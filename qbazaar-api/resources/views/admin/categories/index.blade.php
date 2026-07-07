@extends('admin.layout')

@section('title', 'التصنيفات')
@section('heading', 'التصنيفات')

@section('content')
    {{-- Toolbar --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                    <x-admin.icon name="search" class="size-[18px]" />
                </span>
                <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو المعرّف…"
                       class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                <x-admin.icon name="filter" class="size-[18px]" /> تصفية
            </button>
            @if ($search !== '')
                <a href="{{ route('admin.categories.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
            @endif
        </form>

        <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="plus" class="size-[18px]" /> تصنيف جديد
        </a>
    </div>

    @include('admin.partials.bulk-bar', ['action' => route('admin.categories.bulk-destroy'), 'label' => 'حذف المحدد', 'confirm' => 'حذف العناصر المحددة نهائياً؟'])

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3"><input type="checkbox" id="qb-bulk-all" onclick="qbBulkAll(this)" class="rounded border-ink-300 text-coral"></th>
                        <th class="px-4 py-3 font-semibold">الاسم</th>
                        <th class="px-4 py-3 font-semibold">المعرّف</th>
                        <th class="px-4 py-3 font-semibold">التصنيف الأب</th>
                        <th class="px-4 py-3 font-semibold">الترتيب</th>
                        <th class="px-4 py-3 font-semibold">الحالة</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($categories as $category)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $category->id }}" form="qb-bulk-form" class="qb-bulk-cb rounded border-ink-300 text-coral" onchange="qbBulkSync()"></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="font-semibold hover:text-coral">
                                    {{ $category->getLocalizedName(app()->getLocale()) }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-ink-500">{{ $category->slug }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $category->parent?->getLocalizedName(app()->getLocale()) ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-500">{{ $category->order }}</td>
                            <td class="px-4 py-3">
                                @if ($category->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">مفعّل</span>
                                @else
                                    <span class="rounded-full bg-cream-200 px-2.5 py-1 text-xs font-semibold text-ink-500">معطّل</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.categories.edit', $category) }}" title="تعديل"
                                       class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-coral-soft hover:text-coral">
                                        <x-admin.icon name="pencil" class="size-[18px]" />
                                    </a>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذا التصنيف؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="حذف"
                                                class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-red-50 hover:text-red-600">
                                            <x-admin.icon name="trash" class="size-[18px]" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-admin.icon name="folder" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد تصنيفات مطابقة.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $categories->links() }}
    </div>
@endsection
