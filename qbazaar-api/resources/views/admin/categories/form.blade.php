@extends('admin.layout')

@php($editing = $category->exists)

@section('title', $editing ? 'تعديل تصنيف' : 'تصنيف جديد')
@section('heading', $editing ? 'تعديل تصنيف' : 'تصنيف جديد')

@php($jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)

@section('content')
    <form method="POST"
          action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
          class="mx-auto max-w-4xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-admin.card title="الأسماء">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="name[ar]" label="الاسم (عربي)" :value="old('name.ar', $category->name['ar'] ?? '')" />
                <x-admin.input name="name[en]" label="الاسم (إنجليزي)" :value="old('name.en', $category->name['en'] ?? '')" dir="ltr" />
            </div>
        </x-admin.card>

        <x-admin.card title="عام">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="slug" label="المعرّف (slug)" :value="old('slug', $category->slug)" dir="ltr" />
                <x-admin.select name="parent_id" label="التصنيف الأب">
                    <option value="">— بدون —</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) === $parent->id)>
                            {{ $parent->getLocalizedName(app()->getLocale()) }}
                        </option>
                    @endforeach
                </x-admin.select>
                <x-admin.input name="icon" label="الأيقونة (Lucide)" :value="old('icon', $category->icon)" dir="ltr" />
                <x-admin.input name="order" type="number" label="الترتيب" :value="old('order', $category->order ?? 0)" min="0" />
                <x-admin.checkbox name="is_active" label="مفعّل" :checked="old('is_active', $editing ? $category->is_active : true)" class="sm:col-span-2" />
            </div>
        </x-admin.card>

        <x-admin.card title="إعدادات متقدمة (JSON)" description="مخطط الحقول والمرشحات المخصصة. اتركه فارغًا أو أدخل JSON صحيح.">
            <div class="space-y-4">
                <x-admin.textarea name="custom_fields" label="الحقول المخصصة (custom_fields)" rows="6" dir="ltr" input-class="font-mono text-xs"
                    :value="old('custom_fields', $category->custom_fields !== null ? json_encode($category->custom_fields, $jsonFlags) : '')" />
                <x-admin.textarea name="custom_filters" label="المرشحات المخصصة (custom_filters)" rows="6" dir="ltr" input-class="font-mono text-xs"
                    :value="old('custom_filters', $category->custom_filters !== null ? json_encode($category->custom_filters, $jsonFlags) : '')" />
            </div>
        </x-admin.card>

        <x-admin.form-actions :cancel-url="route('admin.categories.index')" :submit-label="$editing ? 'حفظ التغييرات' : 'إنشاء'" />
    </form>
@endsection
