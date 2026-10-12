@props(['entries' => null, 'empty' => null, 'link' => null])

@php
    // entries: a Collection<TimelineEntry> from EntityTimeline; without it, the slot holds x-admin.timeline-item rows.
    $entries = $entries === null ? null : collect($entries);
    $isEmpty = $entries !== null ? $entries->isEmpty() : $slot->isEmpty();
    $link = is_array($link) ? ['label' => $link['label'] ?? $link[0], 'url' => $link['url'] ?? $link[1]] : null;
@endphp

@if ($isEmpty)
    <x-admin.empty-state compact icon="clock" :message="$empty ?? __('admin.timeline.empty')" {{ $attributes }} />
@else
    <ol {{ $attributes->class('grid') }}>
        @if ($entries !== null)
            @foreach ($entries as $entry)
                <x-admin.timeline-item :title="$entry->title" :actor="$entry->actor" :at="$entry->at" :tone="$entry->tone" />
            @endforeach
        @else
            {{ $slot }}
        @endif
    </ol>
@endif

@if ($link)
    <a href="{{ $link['url'] }}" class="mt-4 inline-flex rounded text-xs font-semibold text-coral-700 hover:underline">{{ $link['label'] }}</a>
@endif
