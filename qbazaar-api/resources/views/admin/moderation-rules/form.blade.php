@extends('admin.layout')

@php($isEdit = $rule->exists)

@section('title', $isEdit ? 'تعديل قاعدة' : 'إضافة قاعدة')
@section('heading', $isEdit ? 'تعديل قاعدة إشراف' : 'إضافة قاعدة إشراف')

@section('content')
    @php
        $currentType = old('type', $rule->type?->value);
        $currentLanguage = old('language', $rule->language?->value ?? 'any');
    @endphp

    <x-admin.back-link :href="route('admin.moderation-rules.index')" class="mb-4">رجوع للقواعد</x-admin.back-link>

    <x-admin.card class="max-w-2xl">
        <form method="POST"
              action="{{ $isEdit ? route('admin.moderation-rules.update', $rule) : route('admin.moderation-rules.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.select name="type" label="النوع" required>
                    <option value="">اختر…</option>
                    @foreach ($types as $case)
                        <option value="{{ $case->value }}" @selected($currentType === $case->value)>{{ $case->label()['ar'] }}</option>
                    @endforeach
                </x-admin.select>

                <x-admin.select name="language" label="اللغة" required>
                    @foreach ($languages as $case)
                        <option value="{{ $case->value }}" @selected($currentLanguage === $case->value)>{{ $case->label()['ar'] }}</option>
                    @endforeach
                </x-admin.select>
            </div>

            <x-admin.input name="value" label="القيمة" :value="old('value', $rule->value)" required maxlength="255" />

            <x-admin.checkbox name="is_active" label="مفعّلة" :checked="old('is_active', $rule->is_active ?? true)" unchecked-value="0" />

            <x-admin.form-actions class="pt-2" :cancel-url="route('admin.moderation-rules.index')" :submit-label="$isEdit ? 'حفظ التغييرات' : 'إضافة'" />
        </form>
    </x-admin.card>
@endsection
