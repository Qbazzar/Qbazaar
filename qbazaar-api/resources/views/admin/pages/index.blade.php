@extends('admin.layout')

@section('title', 'الصفحات')
@section('heading', 'الصفحات')

@section('content')
    <x-admin.page-toolbar description="صفحات المحتوى الثابتة (من نحن، الشروط، الخصوصية…).">
        <x-admin.filter-bar
            :reset-url="route('admin.pages.index')"
            :search="$search"
            placeholder="بحث بالعنوان أو المعرف…"
            :filters="[
                ['name' => 'published', 'label' => 'حالة النشر', 'placeholder' => 'الكل', 'value' => $published,
                    'options' => ['1' => 'منشورة', '0' => 'غير منشورة']],
            ]"
        />
        <x-slot:actions>
            <x-admin.button :href="route('admin.pages.create')" icon="plus">صفحة جديدة</x-admin.button>
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.pages.bulk-destroy')" label="حذف المحدد" confirm="حذف العناصر المحددة نهائياً؟" />

    <x-admin.table
        caption="الصفحات"
        selectable
        :columns="[['label' => 'المعرف', 'secondary' => true], 'العنوان', 'منشورة', ['label' => 'الترتيب', 'secondary' => true], 'آخر تحديث', ['label' => 'إجراءات', 'srOnly' => true]]"
        :empty="$pages->isEmpty()"
        empty-icon="document"
        empty-message="لا توجد صفحات بعد."
    >
        @foreach ($pages as $page)
            <x-admin.table.row>
                <x-admin.table.select :value="$page->id" :label="'تحديد: ' . ($page->title['ar'] ?? $page->slug)" />
                <x-admin.table.cell label="المعرف" secondary class="font-mono text-ink-700">{{ $page->slug }}</x-admin.table.cell>
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.pages.edit', $page) }}" class="font-semibold hover:text-coral-700">{{ $page->title['ar'] ?? '—' }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell label="منشورة">
                    <x-admin.badge :tone="$page->is_published ? 'success' : 'neutral'">{{ $page->is_published ? 'منشورة' : 'مسودة' }}</x-admin.badge>
                </x-admin.table.cell>
                <x-admin.table.cell label="الترتيب" secondary class="text-ink-700">{{ $page->display_order }}</x-admin.table.cell>
                <x-admin.table.cell label="آخر تحديث" class="text-ink-500">{{ optional($page->updated_at)->format('Y-m-d H:i') }}</x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.pages.edit', $page)" icon="pencil" label="تعديل" />
                    <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" data-confirm="هل أنت متأكد من حذف هذه الصفحة؟">
                        @csrf
                        @method('DELETE')
                        <x-admin.icon-button icon="trash" label="حذف" variant="danger" />
                    </form>
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$pages" />
@endsection
