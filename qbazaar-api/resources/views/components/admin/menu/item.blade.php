@props([
    'href' => null,
    'icon' => null,
    'danger' => false,
    'action' => null,
    'method' => 'POST',
    'fields' => [],
    'confirm' => null,
    'confirmTone' => null,
])

@php
    $classes = [
        'flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-start text-sm font-semibold transition md:py-2',
        'text-red-700 hover:bg-red-50' => $danger,
        'text-ink-700 hover:bg-cream-200' => ! $danger,
    ];
    $formId = $action ? \App\Support\Admin\DetachedForm::id($action, $method, $fields) : null;
@endphp

@if ($href)
    <a href="{{ $href }}" data-qb-menu-item {{ $attributes->class($classes) }}>
        @if ($icon)<x-admin.icon :name="$icon" class="size-[18px] shrink-0" />@endif
        {{ $slot }}
    </a>
@elseif ($formId)
    <button type="submit" form="{{ $formId }}" data-qb-menu-item {{ $attributes->class($classes) }}>
        @if ($icon)<x-admin.icon :name="$icon" class="size-[18px] shrink-0" />@endif
        {{ $slot }}
    </button>
    <x-admin.detached-form :id="$formId" :action="$action" :method="$method" :fields="$fields" :confirm="$confirm" :confirm-tone="$confirmTone" />
@else
    <button type="button" data-qb-menu-item {{ $attributes->class($classes) }}>
        @if ($icon)<x-admin.icon :name="$icon" class="size-[18px] shrink-0" />@endif
        {{ $slot }}
    </button>
@endif
