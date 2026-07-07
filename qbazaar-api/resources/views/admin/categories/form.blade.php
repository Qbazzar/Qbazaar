@extends('admin.layout')

@php($editing = $category->exists)

@section('title', $editing ? 'تعديل تصنيف' : 'تصنيف جديد')
@section('heading', $editing ? 'تعديل تصنيف' : 'تصنيف جديد')

@section('content')
    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
          class="max-w-3xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        {{-- Translations --}}
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="mb-4 text-sm font-bold text-ink-700">الأسماء</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">الاسم (عربي)</label>
                    <input type="text" name="name[ar]" value="{{ old('name.ar', $category->name['ar'] ?? '') }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">الاسم (إنجليزي)</label>
                    <input type="text" name="name[en]" value="{{ old('name.en', $category->name['en'] ?? '') }}" dir="ltr"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
            </div>
        </div>

        {{-- General --}}
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="mb-4 text-sm font-bold text-ink-700">عام</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">المعرّف (slug)</label>
                    <input type="text" name="slug" value="{{ old('slug', $category->slug) }}" dir="ltr"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">التصنيف الأب</label>
                    <select name="parent_id"
                            class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                        <option value="">— بدون —</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) === $parent->id)>
                                {{ $parent->getLocalizedName(app()->getLocale()) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">الأيقونة (Lucide)</label>
                    <input type="text" name="icon" value="{{ old('icon', $category->icon) }}" dir="ltr"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">الترتيب</label>
                    <input type="number" name="order" value="{{ old('order', $category->order ?? 0) }}" min="0"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <label class="flex items-center gap-2 sm:col-span-2">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', $editing ? $category->is_active : true))
                           class="h-4 w-4 rounded border-ink-200 text-coral focus:ring-coral">
                    <span class="text-sm font-semibold text-ink-700">مفعّل</span>
                </label>
            </div>
        </div>

        {{-- Advanced JSON schema --}}
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="mb-1 text-sm font-bold text-ink-700">إعدادات متقدمة (JSON)</h2>
            <p class="mb-4 text-xs text-ink-500">مخطط الحقول والمرشحات المخصصة. اتركه فارغًا أو أدخل JSON صحيح.</p>
            <div class="space-y-4">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">الحقول المخصصة (custom_fields)</label>
                    <textarea name="custom_fields" rows="6" dir="ltr"
                              class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 font-mono text-xs outline-none focus:border-coral">{{ old('custom_fields', $category->custom_fields !== null ? json_encode($category->custom_fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">المرشحات المخصصة (custom_filters)</label>
                    <textarea name="custom_filters" rows="6" dir="ltr"
                              class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 font-mono text-xs outline-none focus:border-coral">{{ old('custom_filters', $category->custom_filters !== null ? json_encode($category->custom_filters, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-6 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                <x-admin.icon name="check" class="size-[18px]" /> {{ $editing ? 'حفظ التغييرات' : 'إنشاء' }}
            </button>
            <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-500 transition hover:text-coral">
                <x-admin.icon name="arrow-right" class="size-4" /> إلغاء
            </a>
        </div>
    </form>
@endsection
