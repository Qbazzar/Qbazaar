@props(['value', 'total', 'label', 'tone' => 'warning', 'caption' => null])

{{-- A stock figure has no history to trend, so it is shown as a share of its whole. --}}
@php
    $share = $total > 0 ? min(100, max(0, $value / $total * 100)) : 0;
    $text = number_format($share, $share > 0 && $share < 10 ? 1 : 0) . '%';
    $fill = max(2, $share);
    $mirror = app()->getLocale() === 'ar';
@endphp

<div {{ $attributes }}>
    <div role="img" aria-label="{{ $label }}: {{ number_format($value) }} / {{ number_format($total) }} ({{ $text }})">
        <svg viewBox="0 0 100 8" preserveAspectRatio="none" class="block h-2 w-full" aria-hidden="true" focusable="false">
            <rect x="0" y="0" width="100" height="8" rx="3" fill="var(--viz-track)" />
            @if ($share > 0)
                <rect x="{{ $mirror ? round(100 - $fill, 2) : 0 }}" y="0" width="{{ round($fill, 2) }}" height="8" rx="3" fill="{{ \App\Support\Charts\Palette::css($tone) }}" />
            @endif
        </svg>
    </div>
    <p class="mt-1.5 text-xs text-ink-500"><span dir="ltr" class="font-semibold text-ink-700">{{ $text }}</span> {{ $caption }}</p>
</div>
