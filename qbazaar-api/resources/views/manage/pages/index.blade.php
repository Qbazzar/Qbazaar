@extends('manage.layout')

@section('title', 'الصفحات')
@section('heading', 'الصفحات')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-ink-500">صفحات المحتوى الثابتة (من نحن، الشروط، الخصوصية…).</p>
        <a href="{{ route('manage.pages.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
            <x-manage.icon name="plus" class="size-[18px]" /> صفحة جديدة
        </a>
    </div>

    @include('manage.partials.bulk-bar', ['action' => route('manage.pages.bulk-destroy'), 'label' => 'حذف المحدد', 'confirm' => 'حذف العناصر المحددة نهائياً؟'])

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3"><input type="checkbox" id="qb-bulk-all" onclick="qbBulkAll(this)" class="rounded border-ink-300 text-coral"></th>
                        <th class="px-4 py-3 font-semibold">المعرف</th>
                        <th class="px-4 py-3 font-semibold">العنوان</th>
                        <th class="px-4 py-3 font-semibold">منشورة</th>
                        <th class="px-4 py-3 font-semibold">الترتيب</th>
                        <th class="px-4 py-3 font-semibold">آخر تحديث</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($pages as $page)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $page->id }}" form="qb-bulk-form" class="qb-bulk-cb rounded border-ink-300 text-coral" onchange="qbBulkSync()"></td>
                            <td class="px-4 py-3 font-mono text-ink-700">{{ $page->slug }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('manage.pages.edit', $page) }}" class="font-semibold hover:text-coral">{{ $page->title['ar'] ?? '—' }}</a>
                            </td>
                            <td class="px-4 py-3">
                                @if ($page->is_published)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">منشورة</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-cream-200 px-3 py-1 text-xs font-semibold text-ink-500">مسودة</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-700">{{ $page->display_order }}</td>
                            <td class="px-4 py-3 text-ink-500">{{ optional($page->updated_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('manage.pages.edit', $page) }}" title="تعديل"
                                       class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-coral-soft hover:text-coral">
                                        <x-manage.icon name="pencil" class="size-[18px]" />
                                    </a>
                                    <form method="POST" action="{{ route('manage.pages.destroy', $page) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذه الصفحة؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="حذف"
                                                class="inline-flex size-8 items-center justify-center rounded-lg bg-cream-200 text-ink-700 transition hover:bg-red-50 hover:text-red-600">
                                            <x-manage.icon name="trash" class="size-[18px]" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-cream-200 text-ink-300">
                                    <x-manage.icon name="document" class="size-6" />
                                </span>
                                <p class="text-sm font-semibold text-ink-500">لا توجد صفحات بعد.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $pages->links() }}
    </div>
@endsection
