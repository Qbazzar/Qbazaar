@extends('admin.layout')

@php($editing = $category->exists)

@section('title', $editing ? 'تعديل تصنيف' : 'تصنيف جديد')
@section('heading', $editing ? 'تعديل تصنيف' : 'تصنيف جديد')

@php($jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)

@section('content')
    <x-admin.page
        :title="$editing ? 'تعديل التصنيف' : 'تصنيف جديد'"
        :crumbs="array_filter([
            ['التصنيفات', route('admin.categories.index')],
            $editing ? $category->getLocalizedName('ar') : null,
            $editing ? 'تعديل' : 'تصنيف جديد',
        ])"
        :form="$editing ? [route('admin.categories.update', $category), 'PUT'] : [route('admin.categories.store')]"
        :cancel="route('admin.categories.index')"
        :submit="$editing ? 'حفظ التغييرات' : 'إنشاء'"
    >
        @if ($editing)
            <x-slot:badges>
                <x-admin.badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? 'مفعّل' : 'معطّل' }}</x-admin.badge>
            </x-slot:badges>

            <x-slot:menu>
                @if ($category->is_active)
                    <x-admin.menu.item :href="rtrim((string) config('qbazaar.web_url'), '/') . '/c/' . $category->slug" icon="external" target="_blank" rel="noopener">
                        عرض في الموقع <span class="sr-only">{{ __('admin.shell.opens_new_tab') }}</span>
                    </x-admin.menu.item>
                    <x-admin.menu.divider />
                @endif
                <x-admin.menu.item danger icon="trash" :action="route('admin.categories.destroy', $category)" method="DELETE" confirm="هل أنت متأكد من حذف هذا التصنيف؟">
                    حذف التصنيف
                </x-admin.menu.item>
            </x-slot:menu>
        @endif

        <x-admin.form-section title="الأسماء" description="يظهر الاسم للمستخدمين بلغة الواجهة التي يختارونها." cols="2">
            <x-admin.input name="name[ar]" label="الاسم (عربي)" :value="old('name.ar', $category->name['ar'] ?? '')" required />
            <x-admin.input name="name[en]" label="الاسم (إنجليزي)" :value="old('name.en', $category->name['en'] ?? '')" dir="ltr" required />
        </x-admin.form-section>

        <x-admin.form-section title="إعدادات متقدمة (JSON)" description="مخطط الحقول والمرشحات المخصصة. اتركه فارغًا أو أدخل JSON صحيح.">
            <x-admin.textarea name="custom_fields" label="الحقول المخصصة (custom_fields)" rows="12" dir="ltr" input-class="font-mono text-xs leading-relaxed"
                hint="مفاتيح اختيارية لكل حقل: show_in_card: true لعرض قيمته على بطاقة الإعلان، و option_labels لأسماء خيارات القائمة بالشكل { القيمة: { ar, en } }."
                :value="old('custom_fields', $category->custom_fields !== null ? json_encode($category->custom_fields, $jsonFlags) : '')" />
            <x-admin.textarea name="custom_filters" label="المرشحات المخصصة (custom_filters)" rows="10" dir="ltr" input-class="font-mono text-xs leading-relaxed"
                :value="old('custom_filters', $category->custom_filters !== null ? json_encode($category->custom_filters, $jsonFlags) : '')" />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card title="التنظيم">
                <div class="grid gap-4">
                    <x-admin.select name="parent_id" label="التصنيف الأب">
                        <option value="">— بدون —</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) === $parent->id)>
                                {{ $parent->getLocalizedName(app()->getLocale()) }}
                            </option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.input name="slug" label="المعرّف (slug)" :value="old('slug', $category->slug)" dir="ltr" required
                        hint="أحرف لاتينية وأرقام و - و _ فقط، ويظهر في رابط التصنيف." />
                    <x-admin.input name="icon" label="الأيقونة (Lucide)" :value="old('icon', $category->icon)" dir="ltr" />
                    <x-admin.input name="order" type="number" label="الترتيب" :value="old('order', $category->order ?? 0)" min="0" required />
                    <x-admin.checkbox name="is_active" label="مفعّل" :checked="old('is_active', $editing ? $category->is_active : true)" />
                </div>
            </x-admin.side-card>

            @if ($editing)
                <x-admin.side-card title="بيانات التصنيف">
                    <x-admin.meta-list>
                        @if (isset($category->children_count, $category->ads_count))
                            <x-admin.meta-item label="التصنيفات الفرعية">{{ number_format($category->children_count) }}</x-admin.meta-item>
                            <x-admin.meta-item label="الإعلانات المرتبطة">{{ number_format($category->ads_count) }}</x-admin.meta-item>
                        @endif
                        <x-admin.meta-item label="المعرّف الداخلي" ltr>{{ $category->id }}</x-admin.meta-item>
                        <x-admin.meta-item label="تاريخ الإنشاء" ltr>{{ $category->created_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item label="آخر تعديل" ltr>{{ $category->updated_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                    </x-admin.meta-list>
                    @unless (isset($category->children_count, $category->ads_count))
                        <x-admin.error-state class="mt-3 border-t border-ink-200" :retry-url="request()->fullUrl()" />
                    @endunless
                </x-admin.side-card>
            @endif
        </x-slot:side>
    </x-admin.page>
@endsection
