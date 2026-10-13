@extends('admin.layout')

@php($isEdit = $category->exists)

@section('title', $isEdit ? 'تعديل قسم' : 'قسم جديد')
@section('heading', $isEdit ? 'تعديل قسم' : 'قسم جديد')

@section('content')
    <x-admin.page
        :title="$isEdit ? 'تعديل القسم' : 'قسم جديد'"
        :crumbs="array_filter([
            ['أقسام المساعدة', route('admin.help-categories.index')],
            $isEdit ? ($category->name['ar'] ?? $category->slug) : null,
            $isEdit ? 'تعديل' : 'قسم جديد',
        ])"
        :form="$isEdit ? [route('admin.help-categories.update', $category), 'PUT'] : [route('admin.help-categories.store')]"
        :cancel="route('admin.help-categories.index')"
    >
        @if ($isEdit)
            <x-slot:menu>
                <x-admin.menu.item :href="rtrim((string) config('qbazaar.web_url'), '/') . '/help/c/' . $category->slug" icon="external" target="_blank" rel="noopener">
                    عرض في الموقع <span class="sr-only">{{ __('admin.shell.opens_new_tab') }}</span>
                </x-admin.menu.item>
                <x-admin.menu.divider />
                <x-admin.menu.item danger icon="trash" :action="route('admin.help-categories.destroy', $category)" method="DELETE" confirm="هل أنت متأكد من حذف هذا القسم؟">
                    حذف القسم
                </x-admin.menu.item>
            </x-slot:menu>
        @endif

        <x-admin.form-section title="الاسم" cols="2">
            <x-admin.input name="name_ar" label="الاسم (عربي)" :value="old('name_ar', $category->name['ar'] ?? '')" required />
            <x-admin.input name="name_en" label="الاسم (إنجليزي)" :value="old('name_en', $category->name['en'] ?? '')" dir="ltr" />
        </x-admin.form-section>

        <x-admin.form-section title="الوصف" description="سطر أو سطران يعرّفان القسم، حتى 1000 حرف." cols="2">
            <x-admin.textarea name="description_ar" label="الوصف (عربي)" rows="4" maxlength="1000" :value="old('description_ar', $category->description['ar'] ?? '')" />
            <x-admin.textarea name="description_en" label="الوصف (إنجليزي)" rows="4" maxlength="1000" dir="ltr" :value="old('description_en', $category->description['en'] ?? '')" />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card title="التنظيم">
                <div class="grid gap-4">
                    <x-admin.input name="slug" label="المعرف (Slug)" :value="old('slug', $category->slug)" required
                        hint="يظهر في رابط القسم: /help/c/…" />
                    <x-admin.input name="icon" label="الأيقونة (Lucide)" :value="old('icon', $category->icon)" dir="ltr" />
                    <x-admin.input name="display_order" type="number" label="الترتيب" :value="old('display_order', $category->display_order ?? 0)" min="0" required />
                </div>
            </x-admin.side-card>

            @if ($isEdit)
                <x-admin.side-card title="بيانات القسم" :link="['عرض المقالات', route('admin.help-articles.index', ['category_id' => $category->id])]">
                    <x-admin.meta-list>
                        @if (isset($category->articles_count, $category->published_articles_count))
                            <x-admin.meta-item label="المقالات">{{ number_format($category->articles_count) }}</x-admin.meta-item>
                            <x-admin.meta-item label="المنشورة منها">{{ number_format($category->published_articles_count) }}</x-admin.meta-item>
                        @endif
                        <x-admin.meta-item label="المعرّف الداخلي" ltr>{{ $category->id }}</x-admin.meta-item>
                        <x-admin.meta-item label="تاريخ الإنشاء" ltr>{{ $category->created_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item label="آخر تعديل" ltr>{{ $category->updated_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                    </x-admin.meta-list>
                    @unless (isset($category->articles_count, $category->published_articles_count))
                        <x-admin.error-state class="mt-3 border-t border-ink-200" :retry-url="request()->fullUrl()" />
                    @endunless
                </x-admin.side-card>
            @endif
        </x-slot:side>
    </x-admin.page>
@endsection
