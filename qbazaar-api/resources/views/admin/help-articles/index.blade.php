@extends('admin.layout')

@section('title', 'مقالات المساعدة')
@section('heading', 'مقالات المساعدة')

@section('content')
    <p class="mb-4 text-sm text-ink-500">مقالات مركز المساعدة المعروضة للمستخدمين.</p>

    {{-- Toolbar --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-ink-300">
                    <x-admin.icon name="search" class="size-[18px]" />
                </span>
                <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالعنوان أو المعرف…"
                       class="w-64 rounded-xl border border-ink-200 bg-cream-100 py-2.5 pr-10 pl-4 text-sm outline-none focus:border-coral">
            </div>

            <select name="category_id" class="rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
                <option value="">كل الأقسام</option>
                @foreach ($categories as $id => $label)
                    <option value="{{ $id }}" @selected($categoryId === (string) $id)>{{ $label }}</option>
                @endforeach
            </select>

            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                <x-admin.icon name="filter" class="size-[18px]" /> تصفية
            </button>
            @if ($search !== '' || $categoryId !== '')
                <a href="{{ route('admin.help-articles.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
            @endif
        </form>

        <a href="{{ route('admin.help-articles.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-admin.icon name="plus" class="size-[18px]" /> مقال جديد
        </a>
    </div>

    @include('admin.partials.bulk-bar', ['action' => route('admin.help-articles.bulk-destroy'), 'label' => 'حذف المحدد', 'confirm' => 'حذف العناصر المحددة نهائياً؟'])

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3"><input type="checkbox" id="qb-bulk-all" onclick="qbBulkAll(this)" class="rounded border-ink-300 text-coral"></th>
                        <th class="px-4 py-3 font-semibold">العنوان</th>
                        <th class="px-4 py-3 font-semibold">القسم</th>
                        <th class="px-4 py-3 font-semibold">منشور</th>
                        <th class="px-4 py-3 font-semibold">المشاهدات</th>
                        <th class="px-4 py-3 font-semibold">الترتيب</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($articles as $article)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $article->id }}" form="qb-bulk-form" class="qb-bulk-cb rounded border-ink-300 text-coral" onchange="qbBulkSync()"></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.help-articles.edit', $article) }}" class="font-semibold hover:text-coral">{{ $article->title['ar'] ?? '—' }}</a>
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $article->category?->name['ar'] ?? $article->category?->slug ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($article->is_published)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">منشور</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-cream-200 px-3 py-1 text-xs font-semibold text-ink-500">مسودة</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ number_format($article->views_count) }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $article->display_order }}</td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.help-articles.edit', $article) }}" title="تعديل"
                                       class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-coral-soft hover:text-coral">
                                        <x-admin.icon name="pencil" class="size-[18px]" />
                                    </a>
                                    <form method="POST" action="{{ route('admin.help-articles.destroy', $article) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا المقال؟');">
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
                                    <x-admin.icon name="document" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد مقالات بعد.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $articles->links() }}
    </div>
@endsection
