@props(['user' => null, 'size' => 'sm', 'fallback' => '—', 'link' => true])

@php
    $name = $user?->full_name ?: ($user?->email ?? $fallback);
    $canOpen = $link && $user !== null && auth()->user()?->can('users.view');
    $url = $canOpen ? route('admin.users.show', $user) : null;
@endphp

<span {{ $attributes->class('inline-flex max-w-full min-w-0 items-center gap-2 align-middle') }}>
    @if ($user !== null)
        <x-admin.user-avatar :user="$user" :size="$size" />
    @endif
    @if ($url)
        <a href="{{ $url }}" title="{{ $name }}" class="min-w-0 truncate font-semibold hover:text-coral-700">{{ $name }}</a>
    @else
        <span title="{{ $name }}" class="min-w-0 truncate">{{ $name }}</span>
    @endif
</span>
