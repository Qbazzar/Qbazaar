@extends('manage.layout')

@section('title', 'الصفحات')
@section('heading', 'الصفحات')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-ink-500">صفحات المحتوى الثابتة (من نحن، الشروط، الخصوصية…).</p>
        <a href="{{ route('manage.pages.create') }}" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">صفحة جديدة</a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
                    <tr>
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
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('manage.pages.edit', $page) }}" class="rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">تعديل</a>
                                    <form method="POST" action="{{ route('manage.pages.destroy', $page) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذه الصفحة؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-ink-500">لا توجد صفحات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $pages->links() }}
    </div>
@endsection
