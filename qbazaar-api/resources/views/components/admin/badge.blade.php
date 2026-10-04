@props(['tone' => 'neutral', 'status' => null])

@php
    $isEnumStatus = $status instanceof \App\Enums\Contracts\Badgeable;
    if ($isEnumStatus) {
        $tone = $status->tone();
    }
    $tones = [
        'neutral' => 'bg-cream-200 text-ink-700',
        'success' => 'bg-emerald-50 text-emerald-700',
        'warning' => 'bg-amber-50 text-amber-800',
        'danger' => 'bg-red-50 text-red-700',
        'info' => 'bg-sky-50 text-sky-700',
        'brand' => 'bg-coral-soft text-coral-700',
        'violet' => 'bg-violet-50 text-violet-700',
    ];
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', $tones[$tone] ?? $tones['neutral']]) }}>{{ $isEnumStatus ? $status->label()['ar'] : $slot }}</span>
