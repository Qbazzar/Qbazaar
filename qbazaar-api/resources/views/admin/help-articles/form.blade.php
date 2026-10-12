@extends('admin.layout')

@php($isEdit = $article->exists)
@php($publicUrl = $isEdit ? rtrim((string) config('qbazaar.web_url'), '/') . '/help/articles/' . $article->slug : null)

@section('title', $isEdit ? 'تعديل مقال' : 'مقال جديد')
@section('heading', $isEdit ? 'تعديل مقال' : 'مقال جديد')

@section('content')
    <x-admin.page
        :title="$isEdit ? 'تعديل المقال' : 'مقال جديد'"
        :crumbs="array_filter([
            ['مقالات المساعدة', route('admin.help-articles.index')],
            $isEdit ? ($article->title['ar'] ?? $article->slug) : null,
            $isEdit ? 'تعديل' : 'مقال جديد',
        ])"
        :form="$isEdit ? [route('admin.help-articles.update', $article), 'PUT'] : [route('admin.help-articles.store')]"
        :cancel="route('admin.help-articles.index')"
        dirty-guard
    >
        @if ($isEdit)
            <x-slot:badges>
                <x-admin.badge :tone="$article->is_published ? 'success' : 'neutral'">{{ $article->is_published ? 'منشور' : 'مسودة' }}</x-admin.badge>
            </x-slot:badges>

            <x-slot:menu>
                @if ($article->is_published)
                    <x-admin.menu.item :href="$publicUrl" icon="external" target="_blank" rel="noopener">
                        عرض في الموقع <span class="sr-only">{{ __('admin.shell.opens_new_tab') }}</span>
                    </x-admin.menu.item>
                    <x-admin.menu.divider />
                @endif
                <x-admin.menu.item danger icon="trash" :action="route('admin.help-articles.destroy', $article)" method="DELETE" confirm="هل أنت متأكد من حذف هذا المقال؟">
                    حذف المقال
                </x-admin.menu.item>
            </x-slot:menu>
        @endif

        <x-admin.form-section title="العنوان" cols="2">
            <x-admin.input name="title_ar" label="العنوان (عربي)" :value="old('title_ar', $article->title['ar'] ?? '')" required />
            <x-admin.input name="title_en" label="العنوان (إنجليزي)" :value="old('title_en', $article->title['en'] ?? '')" dir="ltr" />
        </x-admin.form-section>

        <x-admin.form-section title="المقتطف" description="ملخص قصير للمقال، حتى 500 حرف." cols="2">
            <x-admin.textarea name="excerpt_ar" label="ملخص قصير (عربي)" rows="3" maxlength="500" :value="old('excerpt_ar', $article->excerpt['ar'] ?? '')" />
            <x-admin.textarea name="excerpt_en" label="ملخص قصير (إنجليزي)" rows="3" maxlength="500" dir="ltr" :value="old('excerpt_en', $article->excerpt['en'] ?? '')" />
        </x-admin.form-section>

        <x-admin.form-section title="المحتوى" description="المحتوى العربي مطلوب، والإنجليزي اختياري.">
            <x-admin.rich-editor name="body_ar" label="المحتوى (عربي)" dir="rtl" lang="ar" required :value="old('body_ar', $article->body['ar'] ?? '')" />
            <x-admin.rich-editor name="body_en" label="المحتوى (إنجليزي)" dir="ltr" lang="en" :value="old('body_en', $article->body['en'] ?? '')" />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card title="النشر">
                <div class="grid gap-4">
                    <x-admin.select name="category_id" label="القسم" required>
                        <option value="">— اختر القسم —</option>
                        @foreach ($categories as $id => $label)
                            <option value="{{ $id }}" @selected(old('category_id', $article->category_id) === $id)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.checkbox name="is_published" label="منشور" :checked="old('is_published', $article->is_published ?? true)" unchecked-value="0" />
                    <x-admin.input name="slug" label="المعرف (Slug)" :value="old('slug', $article->slug)" required
                        hint="يظهر في رابط المقال: /help/articles/…" />
                    <x-admin.input name="display_order" type="number" label="الترتيب" :value="old('display_order', $article->display_order ?? 0)" min="0" required />
                </div>
            </x-admin.side-card>

            @if ($isEdit)
                <x-admin.side-card title="بيانات المقال">
                    <x-admin.meta-list>
                        <x-admin.meta-item label="المشاهدات">{{ number_format((int) $article->views_count) }}</x-admin.meta-item>
                        <x-admin.meta-item label="الرابط العام" ltr>
                            @if ($article->is_published)
                                <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="rounded text-coral-700 hover:underline">/help/articles/{{ $article->slug }}<span class="sr-only"> {{ __('admin.shell.opens_new_tab') }}</span></a>
                            @else
                                /help/articles/{{ $article->slug }}
                            @endif
                        </x-admin.meta-item>
                        <x-admin.meta-item label="المعرّف الداخلي" ltr>{{ $article->id }}</x-admin.meta-item>
                        <x-admin.meta-item label="تاريخ الإنشاء" ltr>{{ $article->created_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item label="آخر تعديل" ltr>{{ $article->updated_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                    </x-admin.meta-list>
                </x-admin.side-card>
            @endif
        </x-slot:side>
    </x-admin.page>
@endsection
