@extends('admin.layout')

@php($isEdit = $page->exists)

@section('title', $isEdit ? 'تعديل صفحة' : 'صفحة جديدة')
@section('heading', $isEdit ? 'تعديل صفحة' : 'صفحة جديدة')

@section('content')
    <form method="POST"
          action="{{ $isEdit ? route('admin.pages.update', $page) : route('admin.pages.store') }}"
          class="mx-auto max-w-4xl space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-admin.card title="عام">
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.input name="slug" label="المعرف (Slug)" :value="old('slug', $page->slug)" />
                <x-admin.input name="display_order" type="number" label="الترتيب" :value="old('display_order', $page->display_order ?? 0)" />
                <x-admin.input name="published_at" type="datetime-local" label="تاريخ النشر"
                    :value="old('published_at', optional($page->published_at)->format('Y-m-d\TH:i'))" />
                <x-admin.checkbox name="is_published" label="منشورة" :checked="old('is_published', $page->is_published ?? true)" unchecked-value="0" class="md:pt-8" />
            </div>
        </x-admin.card>

        <x-admin.card title="العنوان">
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.input name="title_ar" label="العنوان (عربي)" :value="old('title_ar', $page->title['ar'] ?? '')" />
                <x-admin.input name="title_en" label="العنوان (إنجليزي)" :value="old('title_en', $page->title['en'] ?? '')" dir="ltr" />
            </div>
        </x-admin.card>

        <x-admin.card title="المحتوى">
            <div class="space-y-4">
                <x-admin.textarea name="body_ar" label="المحتوى (عربي)" rows="10" :value="old('body_ar', $page->body['ar'] ?? '')" />
                <x-admin.textarea name="body_en" label="المحتوى (إنجليزي)" rows="10" dir="ltr" :value="old('body_en', $page->body['en'] ?? '')" />
            </div>
        </x-admin.card>

        <x-admin.card title="وصف SEO">
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.textarea name="meta_description_ar" label="الوصف (عربي)" rows="2" :value="old('meta_description_ar', $page->meta_description['ar'] ?? '')" />
                <x-admin.textarea name="meta_description_en" label="الوصف (إنجليزي)" rows="2" dir="ltr" :value="old('meta_description_en', $page->meta_description['en'] ?? '')" />
            </div>
        </x-admin.card>

        <x-admin.form-actions :cancel-url="route('admin.pages.index')" />
    </form>
@endsection
