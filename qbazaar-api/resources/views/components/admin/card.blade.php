@props(['title' => null, 'description' => null, 'icon' => null, 'flush' => false])

<section {{ $attributes->class(['rounded-2xl border border-ink-200 bg-cream-100', 'p-4 sm:p-6' => ! $flush]) }}>
    @if ($title || isset($actions))
        <div @class([
            'flex flex-wrap items-center justify-between gap-3',
            'mb-4' => ! $flush,
            'border-b border-ink-200 px-4 py-4 sm:px-6' => $flush,
        ])>
            <div class="min-w-0">
                @if ($title)
                    <h2 class="flex items-center gap-2 text-sm font-bold text-ink-700">
                        @if ($icon)<x-admin.icon :name="$icon" class="size-[18px] text-coral-600" />@endif
                        {{ $title }}
                    </h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-xs text-ink-500">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
