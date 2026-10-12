@props(['images' => [], 'cover' => true, 'empty' => null])

@php
    // images: [thumb, full?, alt?] or ['thumb' => ..., 'full' => ..., 'alt' => ...]; the first one is the cover.
    $images = collect($images)
        ->map(fn (array $image, int $index): array => [
            'thumb' => $image['thumb'] ?? $image[0],
            'full' => $image['full'] ?? $image[1] ?? null,
            'alt' => $image['alt'] ?? $image[2] ?? __('admin.shell.image', ['number' => $index + 1]),
        ])
        ->values();
@endphp

@if ($images->isEmpty())
    <x-admin.empty-state compact icon="photo" :message="$empty ?? __('admin.shell.no_images')" {{ $attributes }} />
@else
    <ul role="list" {{ $attributes->class('grid grid-cols-2 gap-3 sm:grid-cols-4') }}>
        @foreach ($images as $image)
            @php($isCover = $cover && $loop->first)
            <li @class(['relative overflow-hidden rounded-xl border border-ink-200 bg-cream-200', 'col-span-2 row-span-2' => $isCover])>
                @if ($image['full'])
                    <a href="{{ $image['full'] }}" target="_blank" rel="noopener" class="block aspect-square">
                        <img src="{{ $image['thumb'] }}" alt="{{ $image['alt'] }} {{ __('admin.shell.opens_new_tab') }}" loading="lazy" decoding="async" class="size-full object-cover transition hover:opacity-90">
                    </a>
                @else
                    <img src="{{ $image['thumb'] }}" alt="{{ $image['alt'] }}" loading="lazy" decoding="async" class="aspect-square size-full object-cover">
                @endif
                @if ($isCover)
                    <x-admin.badge tone="brand" class="pointer-events-none absolute start-2 top-2">{{ __('admin.shell.cover') }}</x-admin.badge>
                @endif
            </li>
        @endforeach
    </ul>
@endif
