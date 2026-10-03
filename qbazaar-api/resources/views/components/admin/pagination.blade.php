@props(['paginator'])

@if ($paginator->hasPages())
    <div {{ $attributes->class('mt-6') }}>
        {{ $paginator->onEachSide(1)->links('admin.partials.pagination') }}
    </div>
@endif
