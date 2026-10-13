@php
    $verified = fn (bool $state) => $state ? ['tone' => 'success', 'label' => 'موثّق'] : ['tone' => 'warning', 'label' => 'غير موثّق'];
    $email = $verified((bool) $user->email_verified);
    $phone = $verified((bool) $user->phone_verified);
@endphp

<div class="grid gap-6">
    <x-admin.card title="بيانات الحساب" icon="users" class="@container">
        <x-admin.meta-list class="gap-x-10 @min-[34rem]:grid-cols-2">
            <x-admin.meta-item label="الاسم الكامل">{{ $user->full_name ?: '—' }}</x-admin.meta-item>
            <x-admin.meta-item label="البريد الإلكتروني" ltr>{{ $user->email }}</x-admin.meta-item>
            <x-admin.meta-item label="الهاتف"><x-admin.phone :phone="$user->phone" /></x-admin.meta-item>
            <x-admin.meta-item label="المعرّف" ltr><span class="font-mono text-xs">{{ $user->id }}</span></x-admin.meta-item>
            <x-admin.meta-item label="نوع الحساب">{{ __('admin.home.account_type.' . $user->account_type->value) }}</x-admin.meta-item>
            <x-admin.meta-item label="اللغة">{{ __('admin.locales.' . $user->language->value) }}</x-admin.meta-item>
        </x-admin.meta-list>
    </x-admin.card>

    <x-admin.card title="التحقق" icon="shield" class="@container">
        <x-admin.meta-list class="gap-x-10 @min-[34rem]:grid-cols-2">
            <x-admin.meta-item label="البريد الإلكتروني"><x-admin.badge :tone="$email['tone']">{{ $email['label'] }}</x-admin.badge></x-admin.meta-item>
            <x-admin.meta-item label="الهاتف"><x-admin.badge :tone="$phone['tone']">{{ $phone['label'] }}</x-admin.badge></x-admin.meta-item>
        </x-admin.meta-list>
    </x-admin.card>

    <x-admin.card title="النشاط في السوق" icon="trend" class="@container">
        <dl class="grid grid-cols-2 gap-4 @min-[34rem]:grid-cols-4">
            @foreach ([
                'الإعلانات النشطة' => number_format($user->active_ads_count),
                'التقييم' => $user->rating_count > 0 ? $user->rating_avg . ' (' . number_format($user->rating_count) . ')' : '—',
                'المتابِعون' => number_format($user->followers_count),
                'يتابع' => number_format($user->following_count),
            ] as $label => $value)
                <div class="rounded-xl bg-cream-50 px-4 py-3">
                    <dt class="text-xs text-ink-500">{{ $label }}</dt>
                    <dd class="mt-1 text-lg font-extrabold text-ink-900"><bdi dir="ltr">{{ $value }}</bdi></dd>
                </div>
            @endforeach
        </dl>
    </x-admin.card>

    @if ($business ?? null)
        <x-admin.card title="النشاط التجاري" icon="folder" class="@container">
            <x-admin.meta-list class="gap-x-10 @min-[34rem]:grid-cols-2">
                <x-admin.meta-item label="الاسم التجاري">{{ $business->business_name ?: '—' }}</x-admin.meta-item>
                <x-admin.meta-item label="الاسم القانوني">{{ $business->legal_name ?: '—' }}</x-admin.meta-item>
                <x-admin.meta-item label="السجل التجاري" ltr>{{ $business->commercial_registration_number ?: '—' }}</x-admin.meta-item>
                <x-admin.meta-item label="هاتف التواصل"><x-admin.phone :phone="$business->contact_phone" /></x-admin.meta-item>
                <x-admin.meta-item label="بريد التواصل" ltr>{{ $business->contact_email ?: '—' }}</x-admin.meta-item>
                <x-admin.meta-item label="الموقع الإلكتروني" ltr>{{ $business->website ?: '—' }}</x-admin.meta-item>
                @if ($business->address)
                    <x-admin.meta-item label="العنوان" class="@min-[34rem]:col-span-2">{{ $business->address }}</x-admin.meta-item>
                @endif
            </x-admin.meta-list>
        </x-admin.card>
    @endif
</div>
