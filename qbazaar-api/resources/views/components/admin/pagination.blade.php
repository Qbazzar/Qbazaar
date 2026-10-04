@props(['paginator'])

@if ($paginator->hasPages())
    <div {{ $attributes->class('mt-6') }}>
        @if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
            {{ $paginator->onEachSide(1)->links('admin.partials.pagination') }}
        @else
            {{ $paginator->links('admin.partials.simple-pagination') }}
        @endif
    </div>
@endif
