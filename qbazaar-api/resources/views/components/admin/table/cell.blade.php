@props(['label' => null, 'primary' => false, 'secondary' => false])

<td {{ $attributes->class([
    'md:px-4 md:py-3',
    'hidden lg:table-cell' => $secondary,
    'block pb-1 text-base md:table-cell md:text-sm' => $primary && ! $secondary,
    'flex items-baseline justify-between gap-4 py-1 md:table-cell' => ! $primary && ! $secondary,
]) }}>
    @if ($label && ! $primary)
        <span class="shrink-0 text-xs font-semibold text-ink-500 md:hidden">{{ $label }}</span>
    @endif
    <div @class(['min-w-0', 'text-end md:text-start' => ! $primary])>{{ $slot }}</div>
</td>
