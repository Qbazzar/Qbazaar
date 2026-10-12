@props(['message' => null, 'retryUrl' => null])

{{-- A side widget whose data failed to load (fetched through rescue()), instead of failing the whole page. --}}
<div {{ $attributes->class('px-4 py-6 text-center') }}>
    <span class="mx-auto mb-2 flex size-10 items-center justify-center rounded-full bg-red-50 text-red-700">
        <x-admin.icon name="exclamation" class="size-5" />
    </span>
    <p class="text-sm font-semibold text-ink-700">{{ $message ?? __('admin.shell.widget_failed') }}</p>
    @if ($retryUrl)
        <a href="{{ $retryUrl }}" class="mt-2 inline-flex items-center gap-1.5 rounded text-xs font-semibold text-coral-700 hover:underline">
            <x-admin.icon name="refresh" class="size-4" />
            {{ __('admin.shell.retry') }}
        </a>
    @endif
</div>
