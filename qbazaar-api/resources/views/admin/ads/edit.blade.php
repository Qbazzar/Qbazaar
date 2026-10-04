@extends('admin.layout')

@section('title', 'تعديل إعلان #' . $ad->id)
@section('heading', 'تعديل الإعلان')

@section('content')
    <x-admin.back-link :href="route('admin.ads.show', $ad)" class="mb-4">رجوع للإعلان</x-admin.back-link>

    <form method="POST" action="{{ route('admin.ads.update', $ad) }}" class="mx-auto max-w-4xl space-y-6">
        @csrf
        @method('PUT')

        <x-admin.card title="المحتوى">
            <div class="space-y-4">
                <x-admin.input name="title" label="العنوان" :value="old('title', $ad->title)" required />
                <x-admin.textarea name="description" label="الوصف" :value="old('description', $ad->description)" rows="6" required />
            </div>
        </x-admin.card>

        <div class="grid gap-6 sm:grid-cols-2">
            <x-admin.card title="التصنيف والموقع">
                <div class="space-y-4">
                    <x-admin.select name="category_id" label="التصنيف" required>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('category_id', $ad->category_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.select name="location_id" label="الموقع" required>
                        @foreach ($locations as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('location_id', $ad->location_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </x-admin.select>
                </div>
            </x-admin.card>

            <x-admin.card title="السعر والحالة">
                <div class="space-y-4">
                    <x-admin.input name="price" type="number" label="السعر (ر.ق)" :value="old('price', $ad->price)" step="0.01" min="0" />
                    <x-admin.select name="price_type" label="نوع السعر" required>
                        @foreach ($priceTypes as $case)
                            <option value="{{ $case->value }}" @selected(old('price_type', $ad->price_type?->value) === $case->value)>{{ $case->label()['ar'] }}</option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.select name="condition" label="الحالة (الجودة)">
                        <option value="">—</option>
                        @foreach ($conditions as $case)
                            <option value="{{ $case->value }}" @selected(old('condition', $ad->condition?->value) === $case->value)>{{ $case->label()['ar'] }}</option>
                        @endforeach
                    </x-admin.select>
                </div>
            </x-admin.card>
        </div>

        <x-admin.card title="الإشراف">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <span class="mb-1.5 block text-sm font-semibold text-ink-700">حالة الإعلان</span>
                    <x-admin.badge :status="$ad->status" />
                    <p class="mt-1.5 text-xs text-ink-500">تتغير الحالة من أزرار الإجراءات في صفحة الإعلان.</p>
                </div>
                <x-admin.checkbox name="featured" label="إعلان مميّز" :checked="old('featured', $ad->featured)" unchecked-value="0" class="self-end pb-2.5" />
            </div>
        </x-admin.card>

        <x-admin.form-actions :cancel-url="route('admin.ads.show', $ad)" submit-label="حفظ التعديلات" />
    </form>
@endsection
