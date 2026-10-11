@props(['tabs', 'id'])

<div role="tabpanel" id="{{ $tabs }}-panel-{{ $id }}" aria-labelledby="{{ $tabs }}-tab-{{ $id }}" tabindex="0"
     {{ $attributes->class('mb-6 rounded-2xl outline-hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-coral-600') }}>
    {{ $slot }}
</div>
