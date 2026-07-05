@extends('manage.layout')

@section('title', 'التصنيفات')
@section('heading', 'التصنيفات')

@section('content')
    {{-- Toolbar --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو المعرّف…"
                   class="w-64 rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-sm outline-none focus:border-coral">
            <button type="submit" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">تصفية</button>
            @if ($search !== '')
                <a href="{{ route('manage.categories.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">مسح</a>
            @endif
        </form>

        <a href="{{ route('manage.categories.create') }}" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">
            + تصنيف جديد
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
                    <tr>
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
                            <td class="px-4 py-3">
                                <a href="{{ route('manage.categories.edit', $category) }}" class="font-semibold hover:text-coral">
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
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('manage.categories.edit', $category) }}" class="rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">تعديل</a>
                                    <form method="POST" action="{{ route('manage.categories.destroy', $category) }}"
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذا التصنيف؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-ink-500">لا توجد تصنيفات مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $categories->links() }}
    </div>
@endsection
