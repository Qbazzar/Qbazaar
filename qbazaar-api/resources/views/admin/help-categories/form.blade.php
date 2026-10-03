@extends('admin.layout')

@php($isEdit = $category->exists)

@section('title', $isEdit ? 'تعديل قسم' : 'قسم جديد')
@section('heading', $isEdit ? 'تعديل قسم' : 'قسم جديد')

@section('content')
    <form method="POST"
          action="{{ $isEdit ? route('admin.help-categories.update', $category) : route('admin.help-categories.store') }}"
          class="mx-auto max-w-4xl space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-admin.card title="عام">
            <div class="grid gap-4 md:grid-cols-3">
                <x-admin.input name="slug" label="المعرف (Slug)" :value="old('slug', $category->slug)" />
                <x-admin.input name="icon" label="الأيقونة (Lucide)" :value="old('icon', $category->icon)" dir="ltr" />
                <x-admin.input name="display_order" type="number" label="الترتيب" :value="old('display_order', $category->display_order ?? 0)" />
            </div>
        </x-admin.card>

        <x-admin.card title="الاسم">
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.input name="name_ar" label="الاسم (عربي)" :value="old('name_ar', $category->name['ar'] ?? '')" />
                <x-admin.input name="name_en" label="الاسم (إنجليزي)" :value="old('name_en', $category->name['en'] ?? '')" dir="ltr" />
            </div>
        </x-admin.card>

        <x-admin.card title="الوصف">
            <div class="grid gap-4 md:grid-cols-2">
                <x-admin.textarea name="description_ar" label="الوصف (عربي)" rows="3" :value="old('description_ar', $category->description['ar'] ?? '')" />
                <x-admin.textarea name="description_en" label="الوصف (إنجليزي)" rows="3" dir="ltr" :value="old('description_en', $category->description['en'] ?? '')" />
            </div>
        </x-admin.card>

        <x-admin.form-actions :cancel-url="route('admin.help-categories.index')" />
    </form>
@endsection
