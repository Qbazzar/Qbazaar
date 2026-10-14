{{-- Shown above a list scoped to one ad (?ad=). Needs $adFilter (Ad|null) and $clearUrl. --}}
@if (request()->filled('ad'))
    <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-ink-200 bg-cream-100 px-4 py-2.5 text-[13px]">
        <span class="font-semibold text-ink-500">{{ __('admin.deal.filtered_by_ad') }}</span>
        @if ($adFilter && auth()->user()?->can('ads.view'))
            <a href="{{ route('admin.ads.show', $adFilter) }}" class="min-w-0 truncate font-bold text-ink-900 hover:text-coral-700">{{ $adFilter->title }}</a>
        @else
            <span class="min-w-0 truncate font-bold text-ink-900">{{ $adFilter?->title ?? '#' . request()->string('ad') }}</span>
        @endif
        <a href="{{ $clearUrl }}" class="font-semibold text-coral-700 hover:underline">{{ __('admin.deal.clear_filter') }}</a>
    </div>
@endif
