{{-- Queues that need a person, most urgent first. Empty ones collapse to a quiet "clear" list. --}}
@php
    $waiting = array_values(array_filter($stats['attention'], fn (array $item): bool => $item['count'] > 0));
    $clear = array_values(array_filter($stats['attention'], fn (array $item): bool => $item['count'] === 0));
    $look = [
        'critical' => ['card' => 'border-red-200 bg-red-50', 'bar' => 'bg-red-600', 'badge' => 'bg-red-600 text-white', 'text' => 'text-red-700'],
        'warning' => ['card' => 'border-amber-200 bg-amber-50', 'bar' => 'bg-amber-500', 'badge' => 'bg-amber-500 text-white', 'text' => 'text-amber-800'],
    ];
@endphp

@if ($stats['attention'] !== [])
    <x-admin.card :title="__('admin.home.attention.title')" icon="flag">
        @if ($waiting === [])
            <p class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                <span aria-hidden="true">✓</span> {{ __('admin.home.attention.clear') }}
            </p>
        @else
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[repeat(auto-fit,minmax(15rem,1fr))]">
                @foreach ($waiting as $item)
                    @php
                        $tone = $look[$item['severity']];
                        $tag = $item['href'] ? 'a' : 'div';
                    @endphp
                    <li class="flex">
                        <{{ $tag }} @if ($item['href']) href="{{ $item['href'] }}" @endif
                            @class(['relative flex w-full flex-col gap-1 overflow-hidden rounded-xl border py-3 ps-5 pe-4', $tone['card'], 'transition hover:shadow-sm focus-visible:outline-2 focus-visible:outline-coral-600' => $item['href']])>
                            <span aria-hidden="true" class="absolute inset-y-0 start-0 w-1.5 {{ $tone['bar'] }}"></span>
                            <span class="flex items-start justify-between gap-3">
                                <span class="text-sm font-semibold text-ink-900">{{ __('admin.home.attention.items.' . $item['key']) }}</span>
                                <span dir="ltr" class="shrink-0 rounded-full px-2.5 py-0.5 text-sm font-extrabold {{ $tone['badge'] }}">{{ number_format($item['count']) }}</span>
                            </span>
                            <span class="text-xs font-semibold {{ $tone['text'] }}">{{ __('admin.home.attention.severity.' . $item['severity']) }}</span>
                            <span class="mt-auto space-y-0.5 pt-1 text-xs text-ink-600">
                                @if ($item['oldest'])<span class="block">{{ __('admin.home.attention.oldest', ['time' => $ago($item['oldest'])]) }}</span>@endif
                                @if ($item['amount'] !== null)<span dir="ltr" class="block text-start">{{ __('admin.home.attention.amount', ['amount' => \App\Support\Money::format($item['amount']) . ' QAR']) }}</span>@endif
                            </span>
                        </{{ $tag }}>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($clear !== [])
            <ul class="mt-3 flex flex-wrap items-center gap-2" aria-label="{{ __('admin.home.attention.clear_items') }}">
                @foreach ($clear as $item)
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-cream-200 px-3 py-1 text-xs text-ink-700">
                        <span aria-hidden="true" class="text-emerald-700">✓</span>
                        {{ __('admin.home.attention.items.' . $item['key']) }}
                        <span dir="ltr" class="font-semibold">0</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-admin.card>
@endif
