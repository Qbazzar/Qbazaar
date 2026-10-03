@props([
    'resetUrl',
    'search' => null,
    'placeholder' => 'بحث…',
    'filters' => [],
])

@php
    $isFiltered = filled($search) || collect($filters)->contains(fn (array $filter): bool => filled($filter['value'] ?? null));
    $controlClasses = 'w-full rounded-xl border border-ink-200 bg-cream-100 py-2.5 text-sm text-ink-900 outline-hidden transition focus:border-coral-600 focus:ring-2 focus:ring-coral-600/25';
@endphp

<form method="GET" role="search" {{ $attributes->class('flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center') }}>
    <div class="relative w-full sm:w-64">
        <label for="filter-q" class="sr-only">بحث</label>
        <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-ink-500">
            <x-admin.icon name="search" class="size-[18px]" />
        </span>
        <input id="filter-q" type="search" name="q" value="{{ $search }}" placeholder="{{ $placeholder }}"
               class="{{ $controlClasses }} ps-10 pe-4">
    </div>

    @foreach ($filters as $filter)
        <div class="w-full sm:w-auto">
            <label for="filter-{{ $filter['name'] }}" class="sr-only">{{ $filter['label'] }}</label>
            <select id="filter-{{ $filter['name'] }}" name="{{ $filter['name'] }}" class="{{ $controlClasses }} px-4">
                <option value="">{{ $filter['placeholder'] }}</option>
                @foreach ($filter['options'] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) ($filter['value'] ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        </div>
    @endforeach

    <div class="flex items-center gap-3">
        <x-admin.button icon="filter">تصفية</x-admin.button>
        @if ($isFiltered)
            <a href="{{ $resetUrl }}" class="rounded-lg text-sm font-semibold text-ink-500 transition hover:text-coral-700">مسح</a>
        @endif
    </div>
</form>
