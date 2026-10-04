@props(['value', 'label'])

<td class="block pb-2 md:table-cell md:w-10 md:px-4 md:py-3">
    <input type="checkbox" name="ids[]" value="{{ $value }}" form="qb-bulk-form" data-bulk-item
           aria-label="{{ $label }}" class="size-4 rounded border-ink-300 accent-coral-600">
</td>
