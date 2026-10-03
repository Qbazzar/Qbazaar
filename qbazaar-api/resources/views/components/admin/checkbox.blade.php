@props([
    'name',
    'label',
    'id' => null,
    'value' => '1',
    'checked' => false,
    'uncheckedValue' => null,
])

@php
    $id ??= str_replace(['[]', '[', ']'], ['', '-', ''], $name);
    $error = ($errors ?? null)?->first(str_replace(['[]', '[', ']'], ['', '.', ''], $name));
@endphp

<div {{ $attributes->only('class') }}>
    <div class="flex items-center gap-2">
        @if ($uncheckedValue !== null)
            <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
        @endif
        <input
            type="checkbox"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ $value }}"
            @checked($checked)
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('class')->class('size-4 shrink-0 rounded border-ink-300 accent-coral-600') }}
        >
        <label for="{{ $id }}" class="text-sm font-semibold text-ink-700">{{ $label }}</label>
    </div>
    @if ($error)
        <p id="{{ $id }}-error" class="mt-1 text-xs font-semibold text-red-700">{{ $error }}</p>
    @endif
</div>
