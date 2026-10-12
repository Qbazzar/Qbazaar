@props(['user' => null, 'size' => 'sm', 'decorative' => false])

@php
    $sizes = [
        'xs' => ['box' => 'size-6 text-[10px]', 'px' => 24],
        'sm' => ['box' => 'size-8 text-xs', 'px' => 32],
        'md' => ['box' => 'size-10 text-sm', 'px' => 40],
        'lg' => ['box' => 'size-16 text-xl', 'px' => 64],
    ];
    $dimension = $sizes[$size] ?? $sizes['sm'];
    $tones = [
        'bg-coral-soft text-coral-700',
        'bg-emerald-50 text-emerald-700',
        'bg-sky-50 text-sky-700',
        'bg-violet-50 text-violet-700',
        'bg-amber-50 text-amber-800',
        'bg-cream-200 text-ink-700',
    ];
    $name = trim((string) ($user?->full_name ?? ''));
    $label = $name !== '' ? $name : (string) ($user?->email ?? '');
    $initials = $label === ''
        ? '?'
        : collect(preg_split('/\s+/u', $label, -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $tone = $user === null ? $tones[5] : $tones[crc32((string) $user->getKey()) % 5];
    $photo = $user?->avatarThumbUrl();
@endphp

@if ($photo)
    <img src="{{ $photo }}" alt="{{ $decorative ? '' : $label }}" width="{{ $dimension['px'] }}" height="{{ $dimension['px'] }}" loading="lazy" {{ $attributes->class(['shrink-0 rounded-full border border-ink-200 object-cover', $dimension['box']]) }}>
@elseif ($decorative)
    <span aria-hidden="true" {{ $attributes->class(['inline-flex shrink-0 select-none items-center justify-center rounded-full font-bold', $dimension['box'], $tone]) }}>{{ $initials }}</span>
@else
    <span role="img" aria-label="{{ $label }}" {{ $attributes->class(['inline-flex shrink-0 select-none items-center justify-center rounded-full font-bold', $dimension['box'], $tone]) }}>{{ $initials }}</span>
@endif
