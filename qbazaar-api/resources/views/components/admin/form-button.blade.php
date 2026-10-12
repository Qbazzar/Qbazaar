@props([
    'action',
    'method' => 'POST',
    'fields' => [],
    'confirm' => null,
    'confirmTone' => null,
    'variant' => 'secondary',
    'size' => 'md',
    'icon' => null,
    'block' => false,
])

{{-- A one-click action (approve, suspend…) that is safe to place inside an <x-admin.page form>. --}}
@php($formId = \App\Support\Admin\DetachedForm::id($action, $method, $fields))

<x-admin.button type="submit" form="{{ $formId }}" :variant="$variant" :size="$size" :icon="$icon" :block="$block" {{ $attributes }}>{{ $slot }}</x-admin.button>
<x-admin.detached-form :id="$formId" :action="$action" :method="$method" :fields="$fields" :confirm="$confirm" :confirm-tone="$confirmTone" />
