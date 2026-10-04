@props(['label'])

<div {{ $attributes->class('min-w-0 break-words') }}>
    <dt class="inline text-ink-500">{{ $label }}:</dt>
    <dd class="inline">{{ $slot }}</dd>
</div>
