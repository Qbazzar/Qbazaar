@extends('admin.layout')

@php($isEdit = $category->exists)

@section('title', $isEdit ? 'تعديل قسم' : 'قسم جديد')
@section('heading', $isEdit ? 'تعديل قسم' : 'قسم جديد')

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
          action="{{ $isEdit ? route('admin.help-categories.update', $category) : route('admin.help-categories.store') }}"
          class="max-w-3xl space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        {{-- General --}}
        <div class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="text-sm font-bold text-ink-700">عام</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">المعرف (Slug)</label>
                    <input type="text" name="slug" value="{{ old('slug', $category->slug) }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">الأيقونة (Lucide)</label>
                    <input type="text" name="icon" dir="ltr" value="{{ old('icon', $category->icon) }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">الترتيب</label>
                    <input type="number" name="display_order" value="{{ old('display_order', $category->display_order ?? 0) }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
            </div>
        </div>

        {{-- Name --}}
        <div class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="text-sm font-bold text-ink-700">الاسم</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">الاسم (عربي)</label>
                    <input type="text" name="name_ar" value="{{ old('name_ar', $category->name['ar'] ?? '') }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">الاسم (إنجليزي)</label>
                    <input type="text" name="name_en" dir="ltr" value="{{ old('name_en', $category->name['en'] ?? '') }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
            </div>
        </div>

        {{-- Description --}}
        <div class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="text-sm font-bold text-ink-700">الوصف</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">الوصف (عربي)</label>
                    <textarea name="description_ar" rows="3"
                              class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">{{ old('description_ar', $category->description['ar'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">الوصف (إنجليزي)</label>
                    <textarea name="description_en" rows="3" dir="ltr"
                              class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">{{ old('description_en', $category->description['en'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                <x-admin.icon name="check" class="size-[18px]" /> حفظ
            </button>
            <a href="{{ route('admin.help-categories.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-500 transition hover:text-coral">
                <x-admin.icon name="arrow-right" class="size-4" /> إلغاء
            </a>
        </div>
    </form>
@endsection
