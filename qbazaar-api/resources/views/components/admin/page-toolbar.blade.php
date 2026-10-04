@props(['description' => null])

<div {{ $attributes->class('mb-6') }}>
    @if ($description)
        <p class="mb-4 text-sm text-ink-500">{{ $description }}</p>
    @endif
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <div class="min-w-0 sm:flex-1">{{ $slot }}</div>
        @isset($actions)
            <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
