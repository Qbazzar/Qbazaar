@extends('admin.layout')

@section('title', __('admin.dashboard.title'))
@section('heading', __('admin.dashboard.title'))

@section('content')
    @php
        $locale = app()->getLocale();
        $localized = fn (?array $names): string => $names[$locale] ?? $names['en'] ?? '—';
        $badgeText = fn (?array $badge): string => $badge === null ? '' : (isset($badge['key']) ? __($badge['key']) : $localized($badge));
        $ago = fn (?string $time): string => $time ? \Illuminate\Support\Carbon::parse($time)->diffForHumans() : '—';
        $generatedAt = \Illuminate\Support\Carbon::parse($stats['generated_at']);
        $tileIcons = ['users' => 'users', 'ads' => 'tag', 'orders' => 'banknotes', 'money' => 'banknotes', 'promotions' => 'star', 'engagement' => 'chat', 'support' => 'lifebuoy'];
    @endphp

    <div class="mx-auto w-full max-w-[1600px] space-y-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav aria-label="{{ __('admin.home.period_label') }}" class="inline-flex rounded-xl border border-ink-200 bg-cream-100 p-1">
                @foreach (\App\Services\Admin\Dashboard\DashboardPeriod::cases() as $option)
                    <a href="{{ route('admin.dashboard', ['period' => $option->value]) }}"
                       @if ($option === $period) aria-current="true" @endif
                       @class([
                           'rounded-lg px-4 py-1.5 text-sm font-semibold transition',
                           'bg-coral-soft text-coral-700' => $option === $period,
                           'text-ink-700 hover:bg-cream-200' => $option !== $period,
                       ])>{{ $option->label() }}</a>
                @endforeach
            </nav>
            <p class="text-xs text-ink-500">
                {{ __('admin.home.updated') }}:
                <time datetime="{{ $generatedAt->toIso8601String() }}" title="{{ $generatedAt->toDateTimeString() }}">{{ $generatedAt->diffForHumans() }}</time>
            </p>
        </div>

        @if ($stats['attention'] !== [])
            <x-admin.card :title="__('admin.home.attention.title')" icon="flag">
                <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($stats['attention'] as $item)
                        @php($tag = $item['href'] ? 'a' : 'div')
                        <li>
                            <{{ $tag }} @if ($item['href']) href="{{ $item['href'] }}" @endif
                                @class([
                                    'block rounded-xl border p-4',
                                    'border-amber-200 bg-amber-50' => $item['count'] > 0,
                                    'border-ink-200 bg-cream-50' => $item['count'] === 0,
                                    'transition hover:border-coral-600' => $item['href'],
                                ])>
                                <div class="text-sm font-medium text-ink-700">{{ __('admin.home.attention.items.' . $item['key']) }}</div>
                                <div class="mt-1 text-2xl font-extrabold">{{ number_format($item['count']) }}</div>
                                @if ($item['count'] > 0)
                                    <div class="mt-1 space-y-0.5 text-xs text-ink-600">
                                        @if ($item['oldest'])<div>{{ __('admin.home.attention.oldest', ['time' => $ago($item['oldest'])]) }}</div>@endif
                                        @if ($item['amount'] !== null)<div dir="ltr" class="text-start">{{ __('admin.home.attention.amount', ['amount' => \App\Support\Money::format($item['amount']) . ' QAR']) }}</div>@endif
                                    </div>
                                @endif
                            </{{ $tag }}>
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>
        @endif

        @foreach ($stats['groups'] as $group)
            <section aria-labelledby="group-{{ $group['key'] }}">
                <h2 id="group-{{ $group['key'] }}" class="mb-3 flex items-center gap-2 text-sm font-bold text-ink-500">
                    <x-admin.icon :name="$group['icon']" class="size-4" /> {{ __('admin.home.groups.' . $group['key']) }}
                </h2>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($group['tiles'] as $tile)
                        <x-admin.stat :label="__('admin.home.tiles.' . $tile['key'])" :icon="$tileIcons[$group['key']]" :href="$tile['href'] ?? null">
                            <span dir="ltr" class="block text-start">
                                @if ($tile['kind'] === 'money')
                                    {{ \App\Support\Money::format((string) $tile['value']) }}
                                @elseif ($tile['kind'] === 'percent')
                                    {{ $tile['value'] }}%
                                @else
                                    {{ number_format($tile['value']) }}
                                @endif
                            </span>
                            @if (array_key_exists('change', $tile))
                                <x-admin.trend :change="$tile['change']" class="mt-1" />
                            @endif
                        </x-admin.stat>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($stats['series'] !== [])
            <section aria-labelledby="dashboard-trends">
                <h2 id="dashboard-trends" class="mb-3 flex items-center gap-2 text-sm font-bold text-ink-500">
                    <x-admin.icon name="trend" class="size-4" /> {{ __('admin.home.trends.title') }}
                </h2>
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    @foreach ($stats['series'] as $name => $points)
                        <x-admin.card :title="__('admin.home.trends.' . $name)">
                            <x-admin.bar-chart :points="$points" :label="__('admin.home.trends.' . $name)" />
                        </x-admin.card>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($stats['breakdowns'] !== [])
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach (['categories', 'locations'] as $kind)
                    @php($rows = $stats['breakdowns'][$kind])
                    @php($top = max(1, collect($rows)->max('count') ?? 1))
                    <x-admin.card :title="__('admin.home.breakdowns.' . $kind)" :icon="$kind === 'categories' ? 'folder' : 'map-pin'">
                        @if ($rows === [])
                            <p class="py-6 text-center text-sm text-ink-500">{{ __('admin.home.empty') }}</p>
                        @else
                            <ol class="space-y-3">
                                @foreach ($rows as $row)
                                    <li>
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span class="truncate font-medium">{{ $localized($row['name']) }}</span>
                                            <span class="shrink-0 text-ink-500"><span class="font-bold text-ink-900">{{ number_format($row['count']) }}</span> {{ __('admin.home.breakdowns.ads') }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-cream-200" aria-hidden="true">
                                            <div class="h-full rounded-full bg-coral" style="inline-size: {{ round($row['count'] / $top * 100) }}%"></div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </x-admin.card>
                @endforeach
            </div>
            <p class="text-xs text-ink-500">{{ __('admin.home.breakdowns.search_terms') }}</p>
        @endif

        @if ($stats['recent'] !== [])
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach ($stats['recent'] as $name => $rows)
                    <x-admin.card :title="__('admin.home.recent.' . $name)" flush>
                        @if ($rows === [])
                            <p class="px-6 py-8 text-center text-sm text-ink-500">{{ __('admin.home.empty') }}</p>
                        @else
                            <ul class="divide-y divide-ink-200">
                                @foreach ($rows as $row)
                                    @php($tag = $row['href'] ? 'a' : 'div')
                                    <li>
                                        <{{ $tag }} @if ($row['href']) href="{{ $row['href'] }}" @endif class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6 {{ $row['href'] ? 'hover:bg-cream-50' : '' }}">
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold">{{ is_array($row['title']) ? $localized($row['title']) : $row['title'] }}</span>
                                                <span class="block truncate text-xs text-ink-500">{{ $row['meta'] }}@if ($row['meta'] && $row['at']) · @endif{{ $ago($row['at']) }}</span>
                                            </span>
                                            <span class="flex shrink-0 flex-col items-end gap-1">
                                                @if (isset($row['amount']))<span dir="ltr" class="text-sm font-bold">{{ \App\Support\Money::format($row['amount']) }} QAR</span>@endif
                                                @if ($row['badge'])<x-admin.badge>{{ $badgeText($row['badge']) }}</x-admin.badge>@endif
                                            </span>
                                        </{{ $tag }}>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-admin.card>
                @endforeach
            </div>
        @endif

        @isset($stats['system'])
            @php($system = $stats['system'])
            @php($tone = ['ok' => 'success', 'unavailable' => 'danger', 'stale' => 'danger', 'disabled' => 'neutral', 'unknown' => 'warning'])
            <x-admin.card :title="__('admin.home.system.title')" icon="shield" :description="__('admin.home.system.checked') . ': ' . $ago($system['checked_at'])">
                <dl class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-ink-200 p-4">
                        <dt class="flex items-center justify-between text-sm font-medium text-ink-700">{{ __('admin.home.system.queues') }}
                            <x-admin.badge :tone="$tone[$system['queues']['status']]">{{ __('admin.home.system.status.' . $system['queues']['status']) }}</x-admin.badge></dt>
                        <dd class="mt-2 space-y-0.5 text-xs text-ink-600">
                            @foreach ($system['queues']['sizes'] as $queue => $size)
                                <div class="flex justify-between"><span dir="ltr">{{ $queue }}</span><span>{{ __('admin.home.system.queue_waiting', ['count' => number_format($size)]) }}</span></div>
                            @endforeach
                            <div class="flex justify-between font-semibold"><span>{{ __('admin.home.system.failed_jobs') }}</span><span>{{ number_format($system['failed_jobs']) }}</span></div>
                        </dd>
                    </div>
                    <div class="rounded-xl border border-ink-200 p-4">
                        <dt class="flex items-center justify-between text-sm font-medium text-ink-700">{{ __('admin.home.system.search') }}
                            <x-admin.badge :tone="$tone[$system['search']['status']]">{{ __('admin.home.system.status.' . $system['search']['status']) }}</x-admin.badge></dt>
                        <dd class="mt-2 text-xs text-ink-600">@if ($system['search']['documents'] !== null){{ __('admin.home.system.documents', ['count' => number_format($system['search']['documents'])]) }}@endif</dd>
                    </div>
                    <div class="rounded-xl border border-ink-200 p-4">
                        <dt class="flex items-center justify-between text-sm font-medium text-ink-700">{{ __('admin.home.system.scheduler') }}
                            <x-admin.badge :tone="$tone[$system['scheduler']['status']]">{{ __('admin.home.system.status.' . $system['scheduler']['status']) }}</x-admin.badge></dt>
                        <dd class="mt-2 text-xs text-ink-600">@if ($system['scheduler']['last_run']){{ __('admin.home.system.scheduler_last', ['time' => $ago($system['scheduler']['last_run'])]) }}@endif</dd>
                    </div>
                    <div class="rounded-xl border border-ink-200 p-4">
                        <dt class="flex items-center justify-between text-sm font-medium text-ink-700">{{ __('admin.home.system.storage') }}
                            <x-admin.badge :tone="$tone[$system['storage']['status']]">{{ __('admin.home.system.status.' . $system['storage']['status']) }}</x-admin.badge></dt>
                        <dd class="mt-2 text-xs text-ink-600" dir="ltr">{{ $system['storage']['disk'] }}</dd>
                    </div>
                </dl>
            </x-admin.card>
        @endisset
    </div>
@endsection
