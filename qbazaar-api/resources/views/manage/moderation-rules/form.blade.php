@extends('manage.layout')

@php($isEdit = $rule->exists)

@section('title', $isEdit ? 'تعديل قاعدة' : 'إضافة قاعدة')
@section('heading', $isEdit ? 'تعديل قاعدة إشراف' : 'إضافة قاعدة إشراف')

@php
    $typeLabels = [
        'banned_word' => 'كلمة محظورة',
        'blocked_domain' => 'نطاق محظور',
    ];
    $languageLabels = [
        'any' => 'الكل',
        'ar' => 'العربية',
        'en' => 'الإنجليزية',
    ];
    $currentType = old('type', $rule->type?->value);
    $currentLanguage = old('language', $rule->language?->value ?? 'any');
    $currentActive = old('is_active', $rule->is_active ?? true);
@endphp

@section('content')
    <a href="{{ route('manage.moderation-rules.index') }}" class="mb-4 inline-block text-sm font-semibold text-ink-500 hover:text-coral">→ رجوع</a>

    <div class="max-w-2xl rounded-2xl border border-ink-200 bg-cream-100 p-6">
        <form method="POST"
              action="{{ $isEdit ? route('manage.moderation-rules.update', $rule) : route('manage.moderation-rules.store') }}"
              class="space-y-5">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">النوع</label>
                    <select name="type" required
                            class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                        <option value="">اختر…</option>
                        @foreach ($types as $case)
                            <option value="{{ $case->value }}" @selected($currentType === $case->value)>{{ $typeLabels[$case->value] ?? $case->value }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">اللغة</label>
                    <select name="language" required
                            class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                        @foreach ($languages as $case)
                            <option value="{{ $case->value }}" @selected($currentLanguage === $case->value)>{{ $languageLabels[$case->value] ?? $case->value }}</option>
                        @endforeach
                    </select>
                    @error('language')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink-700">القيمة</label>
                <input type="text" name="value" value="{{ old('value', $rule->value) }}" required maxlength="255"
                       class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                @error('value')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-center gap-2 text-sm font-semibold text-ink-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked($currentActive)
                       class="h-4 w-4 rounded border-ink-200 text-coral focus:ring-coral">
                مفعّلة
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white hover:brightness-95">
                    {{ $isEdit ? 'حفظ التغييرات' : 'إضافة' }}
                </button>
                <a href="{{ route('manage.moderation-rules.index') }}" class="text-sm font-semibold text-ink-500 hover:text-coral">إلغاء</a>
            </div>
        </form>
    </div>
@endsection
