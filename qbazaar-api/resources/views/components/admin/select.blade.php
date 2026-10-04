@props([
    'name',
    'label' => null,
    'id' => null,
    'hint' => null,
    'required' => false,
    'labelHidden' => false,
])

@php
    $id ??= str_replace(['[]', '[', ']'], ['', '-', ''], $name);
    $error = ($errors ?? null)?->first(str_replace(['[]', '[', ']'], ['', '.', ''], $name));
    $describedBy = trim(($hint ? "{$id}-hint " : '') . ($error ? "{$id}-error" : ''));
@endphp

<x-admin.field :for="$id" :label="$label" :hint="$hint" :error="$error" :required="$required" :label-hidden="$labelHidden" class="{{ $attributes->get('class') }}">
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @required($required)
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except('class')->class([
            'w-full rounded-xl border bg-cream-50 px-4 py-2.5 text-sm text-ink-900 outline-hidden transition focus:border-coral-600 focus:ring-2 focus:ring-coral-600/25',
            'border-red-600' => $error,
            'border-ink-200' => ! $error,
        ]) }}
    >
        {{ $slot }}
    </select>
</x-admin.field>
