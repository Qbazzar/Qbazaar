@extends('manage.layout')

@section('title', 'مقالات المساعدة')
@section('heading', 'مقالات المساعدة')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-ink-500">مقالات مركز المساعدة المعروضة للمستخدمين.</p>
        <a href="{{ route('manage.help-articles.create') }}" class="rounded-xl bg-coral px-4 py-2.5 text-sm font-bold text-white hover:brightness-95">مقال جديد</a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 text-xs font-semibold text-ink-500">
                    <tr>
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
                            <td class="px-4 py-3">
                                <a href="{{ route('manage.help-articles.edit', $article) }}" class="font-semibold hover:text-coral">{{ $article->title['ar'] ?? '—' }}</a>
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
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('manage.help-articles.edit', $article) }}" class="rounded-lg bg-cream-200 px-3 py-1.5 text-xs font-semibold hover:bg-ink-200">تعديل</a>
                                    <form method="POST" action="{{ route('manage.help-articles.destroy', $article) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا المقال؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-100">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-ink-500">لا توجد مقالات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {{ $articles->links() }}
    </div>
@endsection
