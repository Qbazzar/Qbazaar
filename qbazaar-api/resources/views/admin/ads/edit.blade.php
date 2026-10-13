@extends('admin.layout')

@section('title', 'تعديل إعلان #' . $ad->id)
@section('heading', 'تعديل الإعلان')

@php
    $staff = auth()->user();
    $seller = $ad->user;
    $media = app(\App\Services\Media\MediaStorage::class);
    $images = $ad->getMedia('images')->values();
    $gallery = $images->map(fn ($image, int $index): array => [
        'thumb' => $media->conversionUrl($image, 'medium'),
        'full' => $media->signedOriginalUrl($image),
        'alt' => 'صورة ' . ($index + 1) . ' من الإعلان',
    ])->all();
    $isActive = $ad->status === \App\Enums\AdStatus::ACTIVE;
    $publicUrl = $ad->isPubliclyListed() ? rtrim((string) config('qbazaar.web_url'), '/') . '/ads/' . $ad->id : null;
    $dateTime = fn ($value): string => $value?->format('Y-m-d H:i') ?? '—';
@endphp

@section('content')
    <x-admin.page title="تعديل الإعلان" :subtitle="$ad->title"
                  :crumbs="[['الإعلانات', route('admin.ads.index')], ['إعلان #' . $ad->id, route('admin.ads.show', $ad)], 'تعديل']"
                  :form="[route('admin.ads.update', $ad), 'PUT']"
                  submit="حفظ التعديلات"
                  :cancel="route('admin.ads.show', $ad)"
                  side-label="إشراف ومعلومات الإعلان"
                  dirty-guard>
        <x-slot:badges>
            <x-admin.badge :status="$ad->status" />
            @if ($ad->featured)
                <x-admin.badge tone="brand">مميّز</x-admin.badge>
            @endif
        </x-slot:badges>

        <x-slot:menu>
            <x-admin.menu.item :href="route('admin.ads.show', $ad)" icon="eye">عرض صفحة الإعلان</x-admin.menu.item>
            @if ($publicUrl)
                <x-admin.menu.item :href="$publicUrl" icon="external" target="_blank" rel="noopener">فتح في الموقع</x-admin.menu.item>
            @endif
            @can('ads.suspend')
                @if ($isActive)
                    <x-admin.menu.item :action="route('admin.ads.force-expire', $ad)" icon="clock"
                                       confirm="إنهاء صلاحية هذا الإعلان؟">إنهاء الصلاحية</x-admin.menu.item>
                    <x-admin.menu.item :action="route('admin.ads.suspend', $ad)" icon="ban"
                                       confirm="إيقاف هذا الإعلان؟ يختفي من الموقع حتى يُرفع الإيقاف.">إيقاف الإعلان</x-admin.menu.item>
                @endif
            @endcan
            @can('ads.delete')
                <x-admin.menu.divider />
                <x-admin.menu.item :action="route('admin.ads.destroy', $ad)" method="DELETE" icon="trash" danger
                                   confirm="حذف هذا الإعلان نهائياً؟ لا يمكن التراجع.">حذف الإعلان</x-admin.menu.item>
            @endcan
        </x-slot:menu>

        {{-- Main column --}}
        <x-admin.form-section id="ad-photos" :title="'الصور (' . $images->count() . ')'" icon="photo"
                              description="تُدار الصور من تطبيق البائع. اضغط على صورة لفتح الأصل.">
            <x-admin.gallery :images="$gallery" empty="لم يرفع البائع صوراً لهذا الإعلان." />
        </x-admin.form-section>

        <x-admin.form-section id="ad-content" title="المحتوى" description="يظهر العنوان والوصف للمشترين كما هما.">
            <x-admin.input name="title" label="العنوان" :value="old('title', $ad->title)" required />
            <x-admin.textarea name="description" label="الوصف" :value="old('description', $ad->description)" rows="7" required />
        </x-admin.form-section>

        <x-admin.form-section id="ad-placement" title="التصنيف والموقع" description="يحدّدان مكان ظهور الإعلان في البحث." cols="2">
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
        </x-admin.form-section>

        <x-admin.form-section id="ad-pricing" title="السعر والحالة" cols="3">
            <x-admin.input name="price" type="number" label="السعر (ر.ق)" :value="old('price', $ad->price)" step="0.01" min="0" dir="ltr" />
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
        </x-admin.form-section>

        {{-- Side column: inside the page form, so the featured box posts with Save. --}}
        <x-slot:side>
            <x-admin.side-card id="ad-moderation" title="الإشراف" icon="shield">
                <x-admin.meta-list>
                    <x-admin.meta-item label="حالة الإعلان"><x-admin.badge :status="$ad->status" /></x-admin.meta-item>
                </x-admin.meta-list>
                <x-admin.checkbox name="featured" label="إعلان مميّز" :checked="old('featured', $ad->featured)" unchecked-value="0" class="mt-4" />
                <p class="mt-2 text-xs text-ink-500">تتغير الحالة من أزرار الإجراءات في صفحة الإعلان.</p>
            </x-admin.side-card>

            <x-admin.side-card id="ad-seller" title="البائع" icon="users"
                               :link="$seller && $staff->can('users.view') ? ['فتح الملف', route('admin.users.show', $seller)] : null">
                @if ($seller)
                    <div class="flex items-center gap-3">
                        <x-admin.user-avatar :user="$seller" size="md" decorative />
                        <div class="min-w-0">
                            <p class="truncate font-bold text-ink-900" title="{{ $seller->full_name }}">{{ $seller->full_name ?: $seller->email }}</p>
                            @if ($seller->phone)
                                <x-admin.phone :phone="$seller->phone" class="text-[13px] text-ink-500" />
                            @endif
                        </div>
                    </div>
                @else
                    <p class="text-ink-500">حساب البائع محذوف.</p>
                @endif
            </x-admin.side-card>

            <x-admin.side-card id="ad-meta" title="بيانات الإعلان" icon="info">
                <x-admin.meta-list>
                    <x-admin.meta-item label="المعرّف" ltr><span class="font-mono text-xs select-all">{{ $ad->id }}</span></x-admin.meta-item>
                    <x-admin.meta-item label="تاريخ الإنشاء" ltr>{{ $dateTime($ad->created_at) }}</x-admin.meta-item>
                    <x-admin.meta-item label="آخر تعديل" ltr>{{ $dateTime($ad->updated_at) }}</x-admin.meta-item>
                    <x-admin.meta-item label="تاريخ النشر" ltr>{{ $dateTime($ad->published_at) }}</x-admin.meta-item>
                    <x-admin.meta-item label="ينتهي في" ltr>{{ $ad->expires_at?->format('Y-m-d') ?? '—' }}</x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>

            <x-admin.side-card id="ad-activity" title="آخر النشاط" icon="activity">
                @if ($history === null)
                    <x-admin.error-state :retry-url="request()->fullUrl()" />
                @else
                    <x-admin.timeline :entries="$history" />
                @endif
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
