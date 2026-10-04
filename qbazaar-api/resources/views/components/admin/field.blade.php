@props(['for', 'label' => null, 'hint' => null, 'error' => null, 'required' => false, 'labelHidden' => false])

<div {{ $attributes }}>
    @if ($label)
        <label for="{{ $for }}" @class(['mb-1.5 block text-sm font-semibold text-ink-700', 'sr-only' => $labelHidden])>
            {{ $label }}@if ($required)<span class="ms-0.5 text-red-700" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p id="{{ $for }}-hint" class="mt-1 text-xs text-ink-500">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $for }}-error" class="mt-1 text-xs font-semibold text-red-700">{{ $error }}</p>
    @endif
</div>
