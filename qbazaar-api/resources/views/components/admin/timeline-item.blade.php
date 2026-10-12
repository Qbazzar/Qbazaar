@props(['title', 'actor' => null, 'at' => null, 'tone' => 'neutral'])

@php
    $dots = [
        'neutral' => 'border-coral',
        'success' => 'border-emerald-600',
        'warning' => 'border-amber-500',
        'danger' => 'border-red-600',
    ];
    $at = $at === null ? null : \Carbon\CarbonImmutable::parse($at);
    // Recent entries read as "2 hours ago"; older ones as a date that stays stable between visits.
    $when = $at === null ? null : ($at->greaterThan(now()->subWeek()) ? $at->diffForHumans() : $at->format('Y-m-d'));
@endphp

<li {{ $attributes->class('relative ps-6 pb-4 text-[13px] last:pb-0 before:absolute before:start-[5px] before:top-[0.45rem] before:-bottom-[0.2rem] before:w-0.5 before:bg-ink-200 last:before:hidden') }}>
    <span aria-hidden="true" class="absolute start-0 top-[0.3rem] size-3 rounded-full border-2 bg-cream-100 {{ $dots[$tone] ?? $dots['neutral'] }}"></span>
    <p class="font-bold text-ink-900">{{ $title }}</p>
    @if ($actor || $at)
        <p class="text-ink-500">
            @if ($actor){{ $actor }}@endif
            @if ($actor && $at)<span aria-hidden="true"> · </span>@endif
            @if ($at)<time datetime="{{ $at->toIso8601String() }}" title="{{ $at->format('Y-m-d H:i') }}">{{ $when }}</time>@endif
        </p>
    @endif
</li>
