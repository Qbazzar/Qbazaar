@extends('admin.layout')

@php($isEdit = $page->exists)

@section('title', $isEdit ? 'تعديل صفحة' : 'صفحة جديدة')
@section('heading', $isEdit ? 'تعديل صفحة' : 'صفحة جديدة')

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
          action="{{ $isEdit ? route('admin.pages.update', $page) : route('admin.pages.store') }}"
          class="mx-auto max-w-4xl space-y-6">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        {{-- General --}}
        <div class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="text-sm font-bold text-ink-700">عام</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">المعرف (Slug)</label>
                    <input type="text" name="slug" value="{{ old('slug', $page->slug) }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">الترتيب</label>
                    <input type="number" name="display_order" value="{{ old('display_order', $page->display_order ?? 0) }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">تاريخ النشر</label>
                    <input type="datetime-local" name="published_at"
                           value="{{ old('published_at', optional($page->published_at)->format('Y-m-d\TH:i')) }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <label class="flex items-center gap-2 pt-7 text-sm font-semibold">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" name="is_published" value="1"
                           @checked(old('is_published', $page->is_published ?? true))
                           class="h-4 w-4 rounded border-ink-200 text-coral focus:ring-coral">
                    منشورة
                </label>
            </div>
        </div>

        {{-- Title --}}
        <div class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="text-sm font-bold text-ink-700">العنوان</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">العنوان (عربي)</label>
                    <input type="text" name="title_ar" value="{{ old('title_ar', $page->title['ar'] ?? '') }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">العنوان (إنجليزي)</label>
                    <input type="text" name="title_en" dir="ltr" value="{{ old('title_en', $page->title['en'] ?? '') }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
            </div>
        </div>

        {{-- Body --}}
        <div class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="text-sm font-bold text-ink-700">المحتوى</h2>
            <div>
                <label class="mb-1.5 block text-sm font-semibold">المحتوى (عربي)</label>
                <textarea name="body_ar" rows="10"
                          class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">{{ old('body_ar', $page->body['ar'] ?? '') }}</textarea>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold">المحتوى (إنجليزي)</label>
                <textarea name="body_en" rows="10" dir="ltr"
                          class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">{{ old('body_en', $page->body['en'] ?? '') }}</textarea>
            </div>
        </div>

        {{-- SEO --}}
        <div class="space-y-4 rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="text-sm font-bold text-ink-700">وصف SEO</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">الوصف (عربي)</label>
                    <textarea name="meta_description_ar" rows="2"
                              class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">{{ old('meta_description_ar', $page->meta_description['ar'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">الوصف (إنجليزي)</label>
                    <textarea name="meta_description_en" rows="2" dir="ltr"
                              class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">{{ old('meta_description_en', $page->meta_description['en'] ?? '') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                <x-admin.icon name="check" class="size-[18px]" /> حفظ
            </button>
            <a href="{{ route('admin.pages.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-500 transition hover:text-coral">
                <x-admin.icon name="arrow-right" class="size-4" /> إلغاء
            </a>
        </div>
    </form>
@endsection
