@extends('admin.layout')

@section('title', 'أقسام المساعدة')
@section('heading', 'أقسام المساعدة')

@section('content')
    <x-admin.page-toolbar description="أقسام مركز المساعدة تصنّف المقالات.">
        <x-admin.filter-bar :reset-url="route('admin.help-categories.index')" :search="$search" placeholder="بحث بالاسم أو المعرف…" />
        <x-slot:actions>
            <x-admin.button :href="route('admin.help-categories.create')" icon="plus">قسم جديد</x-admin.button>
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.help-categories.bulk-destroy')" label="حذف المحدد" confirm="حذف العناصر المحددة نهائياً؟" />

    <x-admin.table
        caption="أقسام المساعدة"
        selectable
        :columns="['الاسم', 'المعرف', ['label' => 'الأيقونة', 'secondary' => true], 'المقالات', ['label' => 'الترتيب', 'secondary' => true], ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$categories->isEmpty()"
        empty-icon="help"
        empty-message="لا توجد أقسام بعد."
    >
        @foreach ($categories as $category)
            <x-admin.table.row>
                <x-admin.table.select :value="$category->id" :label="'تحديد: ' . ($category->name['ar'] ?? $category->slug)" />
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.help-categories.edit', $category) }}" class="font-semibold hover:text-coral-700">{{ $category->name['ar'] ?? '—' }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="المعرف" class="font-mono text-ink-700">{{ $category->slug }}</x-admin.table.cell>
                <x-admin.table.cell label="الأيقونة" secondary class="font-mono text-ink-500">{{ $category->icon ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="المقالات" class="text-ink-700">{{ number_format($category->articles_count) }}</x-admin.table.cell>
                <x-admin.table.cell label="الترتيب" secondary class="text-ink-700">{{ $category->display_order }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.help-categories.edit', $category)" icon="pencil" label="تعديل" />
                    <form method="POST" action="{{ route('admin.help-categories.destroy', $category) }}" data-confirm="هل أنت متأكد من حذف هذا القسم؟">
                        @csrf
                        @method('DELETE')
                        <x-admin.icon-button icon="trash" label="حذف" variant="danger" />
                    </form>
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$categories" />
@endsection
