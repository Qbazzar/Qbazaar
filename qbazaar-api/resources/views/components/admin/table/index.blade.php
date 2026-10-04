@props([
    'columns' => [],
    'empty' => false,
    'emptyIcon' => 'inbox',
    'emptyMessage' => '',
    'selectable' => false,
    'caption' => null,
])

@php
    $columns = array_map(fn (string|array $column): array => is_string($column) ? ['label' => $column] : $column, $columns);
@endphp

<div {{ $attributes->class('overflow-hidden rounded-2xl border border-ink-200 bg-cream-100') }}>
    <div class="md:overflow-x-auto">
        <table class="w-full text-start text-sm max-md:block">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif
            <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500 max-md:hidden">
                <tr>
                    @if ($selectable)
                        <th scope="col" class="w-10 px-4 py-3">
                            <input type="checkbox" data-bulk-all aria-label="تحديد الكل" class="size-4 rounded border-ink-300 accent-coral-600">
                        </th>
                    @endif
                    @foreach ($columns as $column)
                        <th scope="col" @class(['px-4 py-3 text-start font-semibold', 'hidden lg:table-cell' => $column['secondary'] ?? false])>
                            <span @class(['sr-only' => $column['srOnly'] ?? false])>{{ $column['label'] }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-200 max-md:block">
                @if ($empty)
                    <tr class="max-md:block">
                        <td colspan="{{ count($columns) + ($selectable ? 1 : 0) }}" class="max-md:block">
                            <x-admin.empty-state :icon="$emptyIcon" :message="$emptyMessage" />
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>
</div>
