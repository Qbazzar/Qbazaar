@extends('manage.layout')

@section('title', 'أقسام المساعدة')
@section('heading', 'أقسام المساعدة')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-ink-500">أقسام مركز المساعدة تصنّف المقالات.</p>
        <a href="{{ route('manage.help-categories.create') }}" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">قسم جديد</a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">الاسم</th>
                        <th class="px-4 py-3 font-semibold">المعرف</th>
                        <th class="px-4 py-3 font-semibold">الأيقونة</th>
                        <th class="px-4 py-3 font-semibold">المقالات</th>
                        <th class="px-4 py-3 font-semibold">الترتيب</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($categories as $category)
                        <tr class="hover:bg-cream-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('manage.help-categories.edit', $category) }}" class="font-semibold hover:text-coral">{{ $category->name['ar'] ?? '—' }}</a>
                            </td>
                            <td class="px-4 py-3 font-mono text-ink-700">{{ $category->slug }}</td>
                            <td class="px-4 py-3 font-mono text-ink-500">{{ $category->icon ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ number_format($category->articles_count) }}</td>
                            <td class="px-4 py-3 text-ink-700">{{ $category->display_order }}</td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('manage.help-categories.edit', $category) }}" class="rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">تعديل</a>
                                    <form method="POST" action="{{ route('manage.help-categories.destroy', $category) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا القسم؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-ink-500">لا توجد أقسام بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $categories->links() }}
    </div>
@endsection
