@extends('admin.layout')

@section('title', 'مقالات المساعدة')
@section('heading', 'مقالات المساعدة')

@section('content')
    <x-admin.page-toolbar description="مقالات مركز المساعدة المعروضة للمستخدمين.">
        <x-admin.filter-bar
            :reset-url="route('admin.help-articles.index')"
            :search="$search"
            placeholder="بحث بالعنوان أو المعرف…"
            :filters="[
                ['name' => 'category_id', 'label' => 'القسم', 'placeholder' => 'كل الأقسام', 'value' => $categoryId, 'options' => $categories],
            ]"
        />
        <x-slot:actions>
            <x-admin.button :href="route('admin.help-articles.create')" icon="plus">مقال جديد</x-admin.button>
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.help-articles.bulk-destroy')" label="حذف المحدد" confirm="حذف العناصر المحددة نهائياً؟" />

    <x-admin.table
        caption="مقالات المساعدة"
        selectable
        :columns="['العنوان', 'القسم', 'منشور', ['label' => 'المشاهدات', 'secondary' => true], ['label' => 'الترتيب', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$articles->isEmpty()"
        empty-icon="document"
        empty-message="لا توجد مقالات بعد."
    >
        @foreach ($articles as $article)
            <x-admin.table.row>
                <x-admin.table.select :value="$article->id" :label="'تحديد: ' . ($article->title['ar'] ?? $article->slug)" />
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.help-articles.edit', $article) }}" class="font-semibold hover:text-coral-700">{{ $article->title['ar'] ?? '—' }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="القسم" class="text-ink-700">{{ $article->category?->name['ar'] ?? $article->category?->slug ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="منشور">
                    <x-admin.badge :tone="$article->is_published ? 'success' : 'neutral'">{{ $article->is_published ? 'منشور' : 'مسودة' }}</x-admin.badge>
                </x-admin.table.cell>
                <x-admin.table.cell label="المشاهدات" secondary class="text-ink-700">{{ number_format($article->views_count) }}</x-admin.table.cell>
                <x-admin.table.cell label="الترتيب" secondary class="text-ink-700">{{ $article->display_order }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.help-articles.edit', $article)" icon="pencil" label="تعديل" />
                    <form method="POST" action="{{ route('admin.help-articles.destroy', $article) }}" data-confirm="هل أنت متأكد من حذف هذا المقال؟">
                        @csrf
                        @method('DELETE')
                        <x-admin.icon-button icon="trash" label="حذف" variant="danger" />
                    </form>
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$articles" />
@endsection
