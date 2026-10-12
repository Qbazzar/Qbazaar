@props(['ad' => null])

@php
    $media = $ad?->relationLoaded('primaryImage') ? $ad->primaryImage : null;
    $src = $media ? app(\App\Services\Media\MediaStorage::class)->conversionUrl($media, 'thumbnail') : null;
@endphp

@if ($src)
    <img src="{{ $src }}" alt="" width="40" height="40" loading="lazy" {{ $attributes->class('size-10 shrink-0 rounded-lg border border-ink-200 object-cover') }}>
@else
    <span aria-hidden="true" {{ $attributes->class('inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-cream-200 text-ink-500') }}><x-admin.icon name="tag" class="size-5" /></span>
@endif
