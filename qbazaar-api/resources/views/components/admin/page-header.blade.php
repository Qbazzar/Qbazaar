@props(['title', 'subtitle' => null, 'crumbs' => [], 'back' => null, 'sticky' => true])

@php
    $hasActions = trim((string) ($actions ?? '')) !== '';
    $hasMenu = trim((string) ($menu ?? '')) !== '';
@endphp

@if ($sticky)
    {{-- Marks where the header starts: once it scrolls away the header is stuck and shows its hairline. --}}
    <div data-qb-header-sentinel aria-hidden="true" class="-mt-4 h-px sm:-mt-6"></div>
@endif

<header @if ($sticky) data-qb-page-header @endif {{ $attributes->class([
    'mb-6',
    // Bleeds over main's padding so the translucent bar spans the content edge to edge.
    '-mx-4 sticky top-0 z-20 -mt-px border-b border-transparent bg-cream-50/90 px-4 py-3 backdrop-blur-md transition-[border-color,box-shadow] sm:-mx-6 sm:px-6 data-stuck:border-ink-200 data-stuck:shadow-[0_6px_12px_-8px_rgb(0_0_0/0.12)]' => $sticky,
]) }}>
    @if ($crumbs)
        <x-admin.breadcrumb :items="$crumbs" class="mb-1.5" />
    @elseif ($back)
        <div class="mb-1.5"><x-admin.back-link :href="$back">{{ __('admin.shell.back') }}</x-admin.back-link></div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1.5">
            <h1 class="min-w-0 text-xl leading-snug font-extrabold break-words text-ink-900 sm:text-2xl">{{ $title }}</h1>
            {{ $badges ?? '' }}
        </div>

        @if ($hasActions || $hasMenu)
            <div class="ms-auto flex flex-wrap items-center justify-end gap-2">
                {{ $actions ?? '' }}
                @if ($hasMenu)
                    <x-admin.menu>{{ $menu }}</x-admin.menu>
                @endif
            </div>
        @endif
    </div>

    @if ($subtitle)
        <p class="mt-1 text-[13px] text-ink-500">{{ $subtitle }}</p>
    @endif
</header>
