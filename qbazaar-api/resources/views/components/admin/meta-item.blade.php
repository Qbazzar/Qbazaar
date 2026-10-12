@props(['label', 'ltr' => false])

{{-- ltr: ids, dates, phones, emails and amounts keep their own order inside the Arabic row. --}}
<div {{ $attributes->class('flex items-baseline justify-between gap-4') }}>
    <dt class="shrink-0 text-ink-500">{{ $label }}</dt>
    <dd class="min-w-0 text-end font-semibold break-words text-ink-900">
        @if ($ltr)
            <span dir="ltr">{{ $slot }}</span>
        @else
            {{ $slot }}
        @endif
    </dd>
</div>
