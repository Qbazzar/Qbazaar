@props(['url' => null])

@php($base = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-ink-200 px-3 text-sm font-semibold')

@if ($url)
    <a href="{{ $url }}" {{ $attributes->class([$base, 'bg-cream-100 text-ink-700 transition hover:border-coral-600 hover:text-coral-700']) }}>{{ $slot }}</a>
@else
    <span aria-disabled="true" {{ $attributes->class([$base, 'cursor-not-allowed bg-cream-200 text-ink-500']) }}>{{ $slot }}</span>
@endif
