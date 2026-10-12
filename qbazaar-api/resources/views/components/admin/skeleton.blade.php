@props(['lines' => 3, 'avatar' => false])

@php($count = max(1, (int) $lines))

{{-- Placeholder for a panel that is still loading; the shimmer only runs when motion is allowed. --}}
<div role="status" {{ $attributes->class('grid gap-3') }}>
    <span class="sr-only">{{ __('admin.shell.loading') }}</span>
    @if ($avatar)
        <div class="flex items-center gap-3" aria-hidden="true">
            <span class="size-10 shrink-0 rounded-full bg-cream-200 motion-safe:animate-pulse"></span>
            <span class="h-3 w-1/3 rounded-full bg-cream-200 motion-safe:animate-pulse"></span>
        </div>
    @endif
    @for ($line = 1; $line <= $count; $line++)
        {{-- A shorter last line reads as the end of a paragraph. --}}
        <span aria-hidden="true" @class(['block h-3 rounded-full bg-cream-200 motion-safe:animate-pulse', $count > 1 && $line === $count ? 'w-2/3' : 'w-full'])></span>
    @endfor
</div>
