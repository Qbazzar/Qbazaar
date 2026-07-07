@extends('admin.layout')

@section('title', 'تعديل إعلان #' . $ad->id)
@section('heading', 'تعديل الإعلان')

@php
    $priceTypeLabels = ['fixed' => 'سعر ثابت', 'negotiable' => 'قابل للتفاوض', 'free' => 'مجاني', 'contact' => 'بالتواصل'];
    $conditionLabels = ['new' => 'جديد', 'like_new' => 'شبه جديد', 'used' => 'مستعمل'];
    $statusLabels = ['draft' => 'مسودة', 'pending' => 'بانتظار المراجعة', 'active' => 'نشط', 'rejected' => 'مرفوض', 'sold' => 'مباع', 'expired' => 'منتهٍ', 'blocked' => 'موقوف'];
@endphp

@section('content')
    <a href="{{ route('admin.ads.show', $ad) }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-500 transition hover:text-coral">
        <x-admin.icon name="arrow-right" class="size-4" /> رجوع للإعلان
    </a>

    <form method="POST" action="{{ route('admin.ads.update', $ad) }}" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        {{-- General --}}
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="mb-4 font-bold">المحتوى</h2>
            <div class="space-y-4">
                <div>
                    <label for="title" class="mb-1.5 block text-sm font-semibold">العنوان</label>
                    <input id="title" name="title" type="text" required value="{{ old('title', $ad->title) }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                    @error('title')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="mb-1.5 block text-sm font-semibold">الوصف</label>
                    <textarea id="description" name="description" rows="6" required
                              class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">{{ old('description', $ad->description) }}</textarea>
                    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Taxonomy + pricing --}}
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <h2 class="mb-4 font-bold">التصنيف والموقع</h2>
                <div class="space-y-4">
                    <div>
                        <label for="category_id" class="mb-1.5 block text-sm font-semibold">التصنيف</label>
                        <select id="category_id" name="category_id" required
                                class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                            @foreach ($categories as $id => $name)
                                <option value="{{ $id }}" @selected((string) old('category_id', $ad->category_id) === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="location_id" class="mb-1.5 block text-sm font-semibold">الموقع</label>
                        <select id="location_id" name="location_id" required
                                class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                            @foreach ($locations as $id => $name)
                                <option value="{{ $id }}" @selected((string) old('location_id', $ad->location_id) === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('location_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
                <h2 class="mb-4 font-bold">السعر والحالة</h2>
                <div class="space-y-4">
                    <div>
                        <label for="price" class="mb-1.5 block text-sm font-semibold">السعر (ر.ق)</label>
                        <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $ad->price) }}"
                               class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                        @error('price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="price_type" class="mb-1.5 block text-sm font-semibold">نوع السعر</label>
                        <select id="price_type" name="price_type" required
                                class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                            @foreach ($priceTypes as $case)
                                <option value="{{ $case->value }}" @selected(old('price_type', $ad->price_type?->value) === $case->value)>{{ $priceTypeLabels[$case->value] ?? $case->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="condition" class="mb-1.5 block text-sm font-semibold">الحالة (الجودة)</label>
                        <select id="condition" name="condition"
                                class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                            <option value="">—</option>
                            @foreach ($conditions as $case)
                                <option value="{{ $case->value }}" @selected(old('condition', $ad->condition?->value) === $case->value)>{{ $conditionLabels[$case->value] ?? $case->value }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Moderation --}}
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="mb-4 font-bold">الإشراف</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="status" class="mb-1.5 block text-sm font-semibold">حالة الإعلان</label>
                    <select id="status" name="status" required
                            class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                        @foreach ($statuses as $case)
                            <option value="{{ $case->value }}" @selected(old('status', $ad->status?->value) === $case->value)>{{ $statusLabels[$case->value] ?? $case->value }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 self-end pb-2.5 text-sm font-semibold">
                    <input type="hidden" name="featured" value="0">
                    <input type="checkbox" name="featured" value="1" @checked(old('featured', $ad->featured)) class="rounded border-ink-300 text-coral">
                    إعلان مميّز
                </label>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                <x-admin.icon name="check" class="size-[18px]" /> حفظ التعديلات
            </button>
            <a href="{{ route('admin.ads.show', $ad) }}" class="text-sm font-semibold text-ink-500 hover:text-coral">إلغاء</a>
        </div>
    </form>
@endsection
