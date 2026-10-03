@extends('admin.layout')

@php($editing = $location->exists)

@section('title', $editing ? 'تعديل موقع' : 'موقع جديد')
@section('heading', $editing ? 'تعديل موقع' : 'موقع جديد')

@section('content')
    <form method="POST"
          action="{{ $editing ? route('admin.locations.update', $location) : route('admin.locations.store') }}"
          class="mx-auto max-w-4xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-admin.card title="الأسماء">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="name[ar]" label="الاسم (عربي)" :value="old('name.ar', $location->name['ar'] ?? '')" />
                <x-admin.input name="name[en]" label="الاسم (إنجليزي)" :value="old('name.en', $location->name['en'] ?? '')" dir="ltr" />
            </div>
        </x-admin.card>

        <x-admin.card title="عام">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="slug" label="المعرّف (slug)" :value="old('slug', $location->slug)" dir="ltr" />
                <x-admin.select name="type" label="النوع">
                    @foreach ($types as $case)
                        <option value="{{ $case->value }}" @selected(old('type', $location->type?->value) === $case->value)>{{ $case->label()['ar'] }}</option>
                    @endforeach
                </x-admin.select>
                <x-admin.select name="parent_id" label="الموقع الأب">
                    <option value="">— بدون —</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}" @selected(old('parent_id', $location->parent_id) === $parent->id)>
                            {{ $parent->getLocalizedName(app()->getLocale()) }}
                        </option>
                    @endforeach
                </x-admin.select>
                <x-admin.input name="order" type="number" label="الترتيب" :value="old('order', $location->order ?? 0)" min="0" />
            </div>
        </x-admin.card>

        <x-admin.card title="الإحداثيات">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.input name="lat" type="number" label="خط العرض (lat)" :value="old('lat', $location->lat)" step="0.000001" dir="ltr" />
                <x-admin.input name="lng" type="number" label="خط الطول (lng)" :value="old('lng', $location->lng)" step="0.000001" dir="ltr" />
            </div>
        </x-admin.card>

        <x-admin.form-actions :cancel-url="route('admin.locations.index')" :submit-label="$editing ? 'حفظ التغييرات' : 'إنشاء'" />
    </form>
@endsection
