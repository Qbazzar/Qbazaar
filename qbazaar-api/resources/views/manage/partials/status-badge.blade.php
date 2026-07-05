@php
    $value = $status instanceof \App\Enums\AdStatus ? $status->value : $status;
    $styles = [
        'active' => 'bg-emerald-50 text-emerald-700',
        'pending' => 'bg-amber-50 text-amber-700',
        'rejected' => 'bg-red-50 text-red-700',
        'blocked' => 'bg-red-50 text-red-700',
        'sold' => 'bg-sky-50 text-sky-700',
        'draft' => 'bg-cream-200 text-ink-500',
        'expired' => 'bg-cream-200 text-ink-500',
    ];
    $labels = [
        'active' => 'نشط',
        'pending' => 'بانتظار المراجعة',
        'rejected' => 'مرفوض',
        'blocked' => 'موقوف',
        'sold' => 'مباع',
        'draft' => 'مسودة',
        'expired' => 'منتهٍ',
    ];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $styles[$value] ?? 'bg-cream-200 text-ink-500' }}">
    {{ $labels[$value] ?? $value }}
</span>
