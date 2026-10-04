@extends('admin.layout')

@section('title', 'التصنيفات')
@section('heading', 'التصنيفات')

@section('content')
    <x-admin.page-toolbar>
        <x-admin.filter-bar :reset-url="route('admin.categories.index')" :search="$search" placeholder="بحث بالاسم أو المعرّف…" />
        <x-slot:actions>
            <x-admin.button :href="route('admin.categories.create')" icon="plus">تصنيف جديد</x-admin.button>
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.categories.bulk-destroy')" label="حذف المحدد" confirm="حذف العناصر المحددة نهائياً؟" />

    <x-admin.table
        caption="التصنيفات"
        selectable
        :columns="['الاسم', ['label' => 'المعرّف', 'secondary' => true], 'التصنيف الأب', ['label' => 'الترتيب', 'secondary' => true], 'الحالة', ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$categories->isEmpty()"
        empty-icon="folder"
        empty-message="لا توجد تصنيفات مطابقة."
    >
        @foreach ($categories as $category)
            @php($categoryName = $category->getLocalizedName(app()->getLocale()))
            <x-admin.table.row>
                <x-admin.table.select :value="$category->id" :label="'تحديد: ' . $categoryName" />
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.categories.edit', $category) }}" class="font-semibold hover:text-coral-700">{{ $categoryName }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="المعرّف" secondary class="text-ink-500">{{ $category->slug }}</x-admin.table.cell>
                <x-admin.table.cell label="التصنيف الأب" class="text-ink-700">{{ $category->parent?->getLocalizedName(app()->getLocale()) ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell label="الترتيب" secondary class="text-ink-500">{{ $category->order }}</x-admin.table.cell>
                <x-admin.table.cell label="الحالة">
                    <x-admin.badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? 'مفعّل' : 'معطّل' }}</x-admin.badge>
                </x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.categories.edit', $category)" icon="pencil" label="تعديل" />
                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="هل أنت متأكد من حذف هذا التصنيف؟">
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
