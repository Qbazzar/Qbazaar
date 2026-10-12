@props(['points', 'label'])

{{-- Inline SVG bars (no chart library). The table is the accessible twin of the picture. --}}
@php
    $rtl = app()->getLocale() === 'ar';
    $ordered = $rtl ? array_reverse($points) : $points;
    $max = max(1, collect($points)->max('count'));
    $total = collect($points)->sum('count');
    $width = 10;
    $height = 60;
@endphp

<figure>
    <svg viewBox="0 0 {{ count($ordered) * $width }} {{ $height }}" preserveAspectRatio="none" class="h-36 w-full text-coral" role="img" aria-label="{{ $label }}. {{ __('admin.home.trends.total', ['total' => number_format($total)]) }}">
        <title>{{ $label }}</title>
        @foreach ($ordered as $index => $point)
            @php($barHeight = $point['count'] > 0 ? max(2, $point['count'] / $max * ($height - 4)) : 1)
            <rect x="{{ $index * $width + 1 }}" y="{{ $height - $barHeight }}" width="{{ $width - 2 }}" height="{{ $barHeight }}" rx="1" fill="currentColor" opacity="{{ $point['count'] > 0 ? '0.85' : '0.25' }}">
                <title>{{ $point['date'] }}: {{ $point['count'] }}</title>
            </rect>
        @endforeach
    </svg>
    <figcaption class="mt-2 flex flex-wrap justify-between gap-2 text-xs text-ink-500">
        <span>{{ __('admin.home.trends.total', ['total' => number_format($total)]) }}</span>
        <span>{{ __('admin.home.trends.peak', ['peak' => number_format($max === 1 && $total === 0 ? 0 : $max)]) }}</span>
    </figcaption>
    <table class="sr-only">
        <caption>{{ __('admin.home.trends.table_caption', ['series' => $label]) }}</caption>
        <thead><tr><th scope="col">{{ __('admin.home.trends.date') }}</th><th scope="col">{{ __('admin.home.trends.count') }}</th></tr></thead>
        <tbody>
            @foreach ($points as $point)
                <tr><th scope="row">{{ $point['date'] }}</th><td>{{ $point['count'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</figure>
