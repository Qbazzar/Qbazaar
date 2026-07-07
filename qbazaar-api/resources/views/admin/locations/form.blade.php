@extends('admin.layout')

@php($editing = $location->exists)
@php($typeLabels = ['city' => 'مدينة', 'district' => 'منطقة', 'area' => 'حي'])

@section('title', $editing ? 'تعديل موقع' : 'موقع جديد')
@section('heading', $editing ? 'تعديل موقع' : 'موقع جديد')

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
          action="{{ $editing ? route('admin.locations.update', $location) : route('admin.locations.store') }}"
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
                    <input type="text" name="name[ar]" value="{{ old('name.ar', $location->name['ar'] ?? '') }}"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">الاسم (إنجليزي)</label>
                    <input type="text" name="name[en]" value="{{ old('name.en', $location->name['en'] ?? '') }}" dir="ltr"
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
                    <input type="text" name="slug" value="{{ old('slug', $location->slug) }}" dir="ltr"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">النوع</label>
                    <select name="type"
                            class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                        @foreach ($types as $case)
                            <option value="{{ $case->value }}" @selected(old('type', $location->type?->value) === $case->value)>
                                {{ $typeLabels[$case->value] ?? $case->value }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">الموقع الأب</label>
                    <select name="parent_id"
                            class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                        <option value="">— بدون —</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id', $location->parent_id) === $parent->id)>
                                {{ $parent->getLocalizedName(app()->getLocale()) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">الترتيب</label>
                    <input type="number" name="order" value="{{ old('order', $location->order ?? 0) }}" min="0"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
            </div>
        </div>

        {{-- Geo --}}
        <div class="rounded-2xl border border-ink-200 bg-cream-100 p-6">
            <h2 class="mb-4 text-sm font-bold text-ink-700">الإحداثيات</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">خط العرض (lat)</label>
                    <input type="number" step="0.000001" name="lat" value="{{ old('lat', $location->lat) }}" dir="ltr"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink-700">خط الطول (lng)</label>
                    <input type="number" step="0.000001" name="lng" value="{{ old('lng', $location->lng) }}" dir="ltr"
                           class="w-full rounded-xl border border-ink-200 bg-cream-50 px-4 py-2.5 text-sm outline-none focus:border-coral">
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-coral px-6 py-2.5 text-sm font-bold text-white transition hover:brightness-95">
                <x-admin.icon name="check" class="size-[18px]" /> {{ $editing ? 'حفظ التغييرات' : 'إنشاء' }}
            </button>
            <a href="{{ route('admin.locations.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-500 transition hover:text-coral">
                <x-admin.icon name="arrow-right" class="size-4" /> إلغاء
            </a>
        </div>
    </form>
@endsection
