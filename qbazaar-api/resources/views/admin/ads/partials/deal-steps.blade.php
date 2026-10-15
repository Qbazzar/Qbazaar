{{-- The order tracker. Needs $steps (list<OrderStep>); sits inside a container-query card. --}}
@php
    $tones = [
        'done' => ['dot' => 'border-emerald-600 bg-emerald-600 text-white', 'line' => 'before:bg-emerald-600', 'label' => 'text-ink-900'],
        'current' => ['dot' => 'border-coral-600 bg-cream-100 text-coral-700', 'line' => 'before:bg-emerald-600', 'label' => 'text-coral-700'],
        'warning' => ['dot' => 'border-amber-500 bg-amber-50 text-amber-800', 'line' => 'before:bg-emerald-600', 'label' => 'text-amber-800'],
        'failed' => ['dot' => 'border-red-600 bg-red-50 text-red-700', 'line' => 'before:bg-ink-200', 'label' => 'text-red-700'],
        'upcoming' => ['dot' => 'border-ink-200 bg-cream-50 text-ink-300', 'line' => 'before:bg-ink-200', 'label' => 'text-ink-500'],
    ];
    $icons = ['done' => 'check', 'current' => 'clock', 'warning' => 'exclamation', 'failed' => 'x-circle', 'upcoming' => null];
@endphp

<ol aria-label="{{ __('admin.deal.steps.label') }}" class="grid gap-4 @min-[34rem]:flex @min-[34rem]:items-start @min-[34rem]:gap-0">
    @foreach ($steps as $step)
        <li @if ($step->state === 'current') aria-current="step" @endif
            class="relative flex items-center gap-3 @min-[34rem]:flex-1 @min-[34rem]:flex-col @min-[34rem]:gap-2 @min-[34rem]:text-center @min-[34rem]:not-first:before:absolute @min-[34rem]:not-first:before:end-1/2 @min-[34rem]:not-first:before:top-4 @min-[34rem]:not-first:before:h-0.5 @min-[34rem]:not-first:before:w-full @min-[34rem]:not-first:before:content-[''] {{ $tones[$step->state]['line'] }}">
            <span class="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border-2 {{ $tones[$step->state]['dot'] }}">
                @if ($icons[$step->state])
                    <x-admin.icon :name="$icons[$step->state]" class="size-4" />
                @endif
            </span>
            <div class="min-w-0">
                <p class="text-[13px] font-bold {{ $tones[$step->state]['label'] }}">{{ __('admin.deal.order_status.' . $step->key) }}</p>
                @if ($step->at)
                    <p class="text-xs text-ink-500"><time dir="ltr" datetime="{{ $step->at->toIso8601String() }}">{{ $step->at->format('Y-m-d H:i') }}</time></p>
                @endif
                <span class="sr-only">{{ __('admin.deal.steps.' . $step->state) }}</span>
            </div>
        </li>
    @endforeach
</ol>
