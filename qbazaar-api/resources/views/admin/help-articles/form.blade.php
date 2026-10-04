@extends('admin.layout')

@php($isEdit = $article->exists)

@section('title', $isEdit ? 'تعديل مقال' : 'مقال جديد')
@section('heading', $isEdit ? 'تعديل مقال' : 'مقال جديد')

@section('content')
    <form method="POST"
          action="{{ $isEdit ? route('admin.help-articles.update', $article) : route('admin.help-articles.store') }}"
          class="mx-auto max-w-4xl space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-admin.card title="عام">
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.select name="category_id" label="القسم">
                    <option value="">— اختر القسم —</option>
                    @foreach ($categories as $id => $label)
                        <option value="{{ $id }}" @selected(old('category_id', $article->category_id) === $id)>{{ $label }}</option>
                    @endforeach
                </x-admin.select>
                <x-admin.input name="slug" label="المعرف (Slug)" :value="old('slug', $article->slug)" />
                <x-admin.input name="display_order" type="number" label="الترتيب" :value="old('display_order', $article->display_order ?? 0)" />
                <x-admin.checkbox name="is_published" label="منشور" :checked="old('is_published', $article->is_published ?? true)" unchecked-value="0" class="md:pt-8" />
            </div>
        </x-admin.card>

        <x-admin.card title="العنوان">
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.input name="title_ar" label="العنوان (عربي)" :value="old('title_ar', $article->title['ar'] ?? '')" />
                <x-admin.input name="title_en" label="العنوان (إنجليزي)" :value="old('title_en', $article->title['en'] ?? '')" dir="ltr" />
            </div>
        </x-admin.card>

        <x-admin.card title="المقتطف">
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.textarea name="excerpt_ar" label="ملخص قصير (عربي)" rows="2" :value="old('excerpt_ar', $article->excerpt['ar'] ?? '')" />
                <x-admin.textarea name="excerpt_en" label="ملخص قصير (إنجليزي)" rows="2" dir="ltr" :value="old('excerpt_en', $article->excerpt['en'] ?? '')" />
            </div>
        </x-admin.card>

        <x-admin.card title="المحتوى">
            <div class="space-y-4">
                <x-admin.textarea name="body_ar" label="المحتوى (عربي)" rows="10" :value="old('body_ar', $article->body['ar'] ?? '')" />
                <x-admin.textarea name="body_en" label="المحتوى (إنجليزي)" rows="10" dir="ltr" :value="old('body_en', $article->body['en'] ?? '')" />
            </div>
        </x-admin.card>

        <x-admin.form-actions :cancel-url="route('admin.help-articles.index')" />
    </form>
@endsection
