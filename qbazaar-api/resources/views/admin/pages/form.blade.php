@extends('admin.layout')

@php($isEdit = $page->exists)
@php($publicUrl = $isEdit ? rtrim((string) config('qbazaar.web_url'), '/') . '/p/' . $page->slug : null)

@section('title', $isEdit ? 'تعديل صفحة' : 'صفحة جديدة')
@section('heading', $isEdit ? 'تعديل صفحة' : 'صفحة جديدة')

@section('content')
    <x-admin.page
        :title="$isEdit ? 'تعديل الصفحة' : 'صفحة جديدة'"
        :crumbs="array_filter([
            ['الصفحات', route('admin.pages.index')],
            $isEdit ? ($page->title['ar'] ?? $page->slug) : null,
            $isEdit ? 'تعديل' : 'صفحة جديدة',
        ])"
        :form="$isEdit ? [route('admin.pages.update', $page), 'PUT'] : [route('admin.pages.store')]"
        :cancel="route('admin.pages.index')"
        dirty-guard
    >
        @if ($isEdit)
            <x-slot:badges>
                <x-admin.badge :tone="$page->is_published ? 'success' : 'neutral'">{{ $page->is_published ? 'منشورة' : 'مسودة' }}</x-admin.badge>
            </x-slot:badges>

            <x-slot:menu>
                @if ($page->is_published)
                    <x-admin.menu.item :href="$publicUrl" icon="external" target="_blank" rel="noopener">
                        عرض في الموقع <span class="sr-only">{{ __('admin.shell.opens_new_tab') }}</span>
                    </x-admin.menu.item>
                    <x-admin.menu.divider />
                @endif
                <x-admin.menu.item danger icon="trash" :action="route('admin.pages.destroy', $page)" method="DELETE" confirm="هل أنت متأكد من حذف هذه الصفحة؟">
                    حذف الصفحة
                </x-admin.menu.item>
            </x-slot:menu>
        @endif

        <x-admin.form-section title="العنوان" cols="2">
            <x-admin.input name="title_ar" label="العنوان (عربي)" :value="old('title_ar', $page->title['ar'] ?? '')" required />
            <x-admin.input name="title_en" label="العنوان (إنجليزي)" :value="old('title_en', $page->title['en'] ?? '')" dir="ltr" />
        </x-admin.form-section>

        <x-admin.form-section title="المحتوى" description="المحتوى العربي مطلوب، والإنجليزي اختياري.">
            <x-admin.rich-editor name="body_ar" label="المحتوى (عربي)" dir="rtl" lang="ar" required :value="old('body_ar', $page->body['ar'] ?? '')" />
            <x-admin.rich-editor name="body_en" label="المحتوى (إنجليزي)" dir="ltr" lang="en" :value="old('body_en', $page->body['en'] ?? '')" />
        </x-admin.form-section>

        <x-admin.form-section title="وصف SEO" description="وصف مختصر لمحركات البحث، حتى 500 حرف." cols="2">
            <x-admin.textarea name="meta_description_ar" label="الوصف (عربي)" rows="3" maxlength="500" :value="old('meta_description_ar', $page->meta_description['ar'] ?? '')" />
            <x-admin.textarea name="meta_description_en" label="الوصف (إنجليزي)" rows="3" maxlength="500" dir="ltr" :value="old('meta_description_en', $page->meta_description['en'] ?? '')" />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card title="النشر">
                <div class="grid gap-4">
                    <x-admin.checkbox name="is_published" label="منشورة" :checked="old('is_published', $page->is_published ?? true)" unchecked-value="0" />
                    <x-admin.input name="published_at" type="datetime-local" label="تاريخ النشر" dir="ltr"
                        :value="old('published_at', optional($page->published_at)->format('Y-m-d\TH:i'))" />
                    <x-admin.input name="slug" label="المعرف (Slug)" :value="old('slug', $page->slug)" required
                        hint="يظهر في رابط الصفحة: /p/…" />
                    <x-admin.input name="display_order" type="number" label="الترتيب" :value="old('display_order', $page->display_order ?? 0)" min="0" required />
                </div>
            </x-admin.side-card>

            @if ($isEdit)
                <x-admin.side-card title="بيانات الصفحة">
                    <x-admin.meta-list>
                        <x-admin.meta-item label="الرابط العام" ltr>
                            @if ($page->is_published)
                                <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="rounded text-coral-700 hover:underline">/p/{{ $page->slug }}<span class="sr-only"> {{ __('admin.shell.opens_new_tab') }}</span></a>
                            @else
                                /p/{{ $page->slug }}
                            @endif
                        </x-admin.meta-item>
                        <x-admin.meta-item label="المعرّف الداخلي" ltr>{{ $page->id }}</x-admin.meta-item>
                        <x-admin.meta-item label="تاريخ الإنشاء" ltr>{{ $page->created_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item label="آخر تعديل" ltr>{{ $page->updated_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                    </x-admin.meta-list>
                </x-admin.side-card>
            @endif
        </x-slot:side>
    </x-admin.page>
@endsection
