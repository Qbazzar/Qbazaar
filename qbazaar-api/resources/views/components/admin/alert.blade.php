@props(['tone' => 'info', 'title' => null, 'summary' => null, 'autofocus' => false])

@php
    $tones = [
        'danger' => ['box' => 'border-red-200 bg-red-50 text-red-700', 'icon' => 'exclamation', 'role' => 'alert'],
        'warning' => ['box' => 'border-amber-200 bg-amber-50 text-amber-800', 'icon' => 'exclamation', 'role' => 'alert'],
        'info' => ['box' => 'border-sky-200 bg-sky-50 text-sky-700', 'icon' => 'info', 'role' => 'status'],
        'success' => ['box' => 'border-emerald-200 bg-emerald-50 text-emerald-700', 'icon' => 'check', 'role' => 'status'],
    ];
    $style = $tones[$tone] ?? $tones['info'];
    // summary: the validation errors ($errors). Each field's first message links to the input,
    // whose id the x-admin.input family derives from the name the same way (name[ar] -> name-ar).
    $messages = match (true) {
        $summary instanceof \Illuminate\Support\ViewErrorBag => $summary->getBag('default')->getMessages(),
        $summary instanceof \Illuminate\Contracts\Support\MessageBag => $summary->getMessages(),
        is_array($summary) => $summary,
        default => [],
    };
@endphp

<div role="{{ $style['role'] }}"
     @if ($autofocus) tabindex="-1" data-qb-autofocus @endif
     {{ $attributes->class(['flex gap-3 rounded-2xl border px-4 py-3.5 text-sm', $style['box']]) }}>
    <x-admin.icon :name="$style['icon']" class="mt-0.5 size-5 shrink-0" />
    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-bold">{{ $title }}</p>
        @endif
        @if ($messages !== [])
            <ul @class(['grid list-disc gap-1 ps-5', 'mt-1.5' => $title])>
                @foreach ($messages as $field => $fieldMessages)
                    <li>
                        <a href="#{{ str_replace(['.', '[', ']'], ['-', '-', ''], (string) $field) }}" data-qb-field-link
                           class="font-semibold underline underline-offset-2 hover:no-underline">{{ is_array($fieldMessages) ? ($fieldMessages[0] ?? '') : $fieldMessages }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
        {{ $slot }}
    </div>
</div>
