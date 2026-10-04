@props(['cancelUrl' => null, 'submitLabel' => 'حفظ', 'icon' => 'check'])

<div {{ $attributes->class('flex flex-wrap items-center gap-3') }}>
    <x-admin.button :icon="$icon">{{ $submitLabel }}</x-admin.button>
    @if ($cancelUrl)
        <x-admin.back-link :href="$cancelUrl">إلغاء</x-admin.back-link>
    @endif
    {{ $slot }}
</div>
