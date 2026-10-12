@props(['title', 'description' => null, 'icon' => null, 'cols' => 1, 'id' => null])

@php
    $id ??= 'section-' . (\Illuminate\Support\Str::slug((string) $title) ?: substr(md5((string) $title), 0, 8));
    // Columns follow the card's own width (container query), so a section reflows the same
    // way in the main column beside the sidebar as at full width. Long fields add col-span-full.
    $grid = match ((int) $cols) {
        2 => 'grid gap-x-6 gap-y-5 @min-[34rem]:grid-cols-2',
        3 => 'grid gap-x-6 gap-y-5 @min-[34rem]:grid-cols-2 @min-[46rem]:grid-cols-3',
        default => 'grid gap-5',
    };
@endphp

<section id="{{ $id }}" aria-labelledby="{{ $id }}-title" {{ $attributes->class('@container rounded-2xl border border-ink-200 bg-cream-100') }}>
    <div class="flex items-start justify-between gap-3 border-b border-ink-200 px-4 py-4 sm:px-6 sm:py-5">
        <div class="min-w-0">
            <h2 id="{{ $id }}-title" class="flex items-center gap-2 text-[15px] font-bold text-ink-900">
                @if ($icon)<x-admin.icon :name="$icon" class="size-[18px] shrink-0 text-coral-600" />@endif
                {{ $title }}
            </h2>
            @if ($description)
                <p class="mt-1 text-[13px] text-ink-500">{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
    <div class="p-4 sm:p-6">
        <div class="{{ $grid }}">
            {{ $slot }}
        </div>
    </div>
</section>
