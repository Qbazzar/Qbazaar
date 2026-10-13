@extends('admin.layout')

@php($editing = $location->exists)

@section('title', $editing ? 'تعديل موقع' : 'موقع جديد')
@section('heading', $editing ? 'تعديل موقع' : 'موقع جديد')

@section('content')
    <x-admin.page
        :title="$editing ? 'تعديل الموقع' : 'موقع جديد'"
        :crumbs="array_filter([
            ['المواقع', route('admin.locations.index')],
            $editing ? $location->getLocalizedName('ar') : null,
            $editing ? 'تعديل' : 'موقع جديد',
        ])"
        :form="$editing ? [route('admin.locations.update', $location), 'PUT'] : [route('admin.locations.store')]"
        :cancel="route('admin.locations.index')"
        :submit="$editing ? 'حفظ التغييرات' : 'إنشاء'"
    >
        @if ($editing)
            @if ($location->type)
                <x-slot:badges>
                    <x-admin.badge>{{ $location->type->label()['ar'] }}</x-admin.badge>
                </x-slot:badges>
            @endif

            <x-slot:menu>
                <x-admin.menu.item danger icon="trash" :action="route('admin.locations.destroy', $location)" method="DELETE" confirm="هل أنت متأكد من حذف هذا الموقع؟">
                    حذف الموقع
                </x-admin.menu.item>
            </x-slot:menu>
        @endif

        <x-admin.form-section title="الأسماء" description="يظهر الاسم للمستخدمين بلغة الواجهة التي يختارونها." cols="2">
            <x-admin.input name="name[ar]" label="الاسم (عربي)" :value="old('name.ar', $location->name['ar'] ?? '')" required />
            <x-admin.input name="name[en]" label="الاسم (إنجليزي)" :value="old('name.en', $location->name['en'] ?? '')" dir="ltr" required />
        </x-admin.form-section>

        <x-admin.form-section title="الإحداثيات" description="اختيارية، بالدرجات العشرية، مثل 25.285447 و 51.531040 للدوحة." cols="2">
            <x-admin.input name="lat" type="number" label="خط العرض (lat)" :value="old('lat', $location->lat)" step="0.000001" min="-90" max="90" dir="ltr" />
            <x-admin.input name="lng" type="number" label="خط الطول (lng)" :value="old('lng', $location->lng)" step="0.000001" min="-180" max="180" dir="ltr" />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card title="التنظيم">
                <div class="grid gap-4">
                    <x-admin.select name="type" label="النوع" required>
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
                    <x-admin.input name="slug" label="المعرّف (slug)" :value="old('slug', $location->slug)" dir="ltr" required
                        hint="أحرف لاتينية وأرقام و - و _ فقط." />
                    <x-admin.input name="order" type="number" label="الترتيب" :value="old('order', $location->order ?? 0)" min="0" required />
                </div>
            </x-admin.side-card>

            @if ($editing)
                <x-admin.side-card title="بيانات الموقع">
                    <x-admin.meta-list>
                        @if (isset($location->children_count, $location->ads_count))
                            <x-admin.meta-item label="المواقع الفرعية">{{ number_format($location->children_count) }}</x-admin.meta-item>
                            <x-admin.meta-item label="الإعلانات المرتبطة">{{ number_format($location->ads_count) }}</x-admin.meta-item>
                        @endif
                        <x-admin.meta-item label="المعرّف الداخلي" ltr>{{ $location->id }}</x-admin.meta-item>
                        <x-admin.meta-item label="تاريخ الإنشاء" ltr>{{ $location->created_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item label="آخر تعديل" ltr>{{ $location->updated_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                    </x-admin.meta-list>
                    @unless (isset($location->children_count, $location->ads_count))
                        <x-admin.error-state class="mt-3 border-t border-ink-200" :retry-url="request()->fullUrl()" />
                    @endunless
                </x-admin.side-card>
            @endif
        </x-slot:side>
    </x-admin.page>
@endsection
