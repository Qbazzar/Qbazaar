{{-- Recent activity in one tabbed card, beside the figures that did not earn a card of their own. --}}
@php
    $badgeText = fn (?array $badge): string => $badge === null ? '' : (isset($badge['key']) ? __($badge['key']) : $localized($badge));
    $recentTabs = [];
    foreach (array_keys($stats['recent']) as $name) {
        $recentTabs[$name] = ['label' => __('admin.home.recent_tabs.' . $name)];
    }
    $moreTiles = $home->moreTiles();
@endphp

@if ($stats['recent'] !== [] || $moreTiles->isNotEmpty())
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-12">
        @if ($stats['recent'] !== [])
            <x-admin.card :title="__('admin.home.recent.title')" icon="flag" class="md:col-span-2 {{ $moreTiles->isNotEmpty() ? 'xl:col-span-8' : 'xl:col-span-12' }}">
                <x-admin.tabs :tabs="$recentTabs" id="recent" :label="__('admin.home.recent.title')">
                    @foreach ($stats['recent'] as $name => $rows)
                        <x-admin.tab-panel tabs="recent" :id="$name" class="mb-0!">
                            <h3 class="sr-only">{{ __('admin.home.recent.' . $name) }}</h3>
                            @if ($rows === [])
                                <p class="py-10 text-center text-sm text-ink-500">{{ __('admin.home.empty') }}</p>
                            @else
                                <ul class="divide-y divide-ink-200">
                                    @foreach ($rows as $row)
                                        @php($tag = $row['href'] ? 'a' : 'div')
                                        <li>
                                            <{{ $tag }} @if ($row['href']) href="{{ $row['href'] }}" @endif class="flex items-center gap-3 rounded-lg px-2 py-3 {{ $row['href'] ? 'hover:bg-cream-50' : '' }}">
                                                @if ($row['person'] ?? null)
                                                    <x-admin.user-avatar :user="\App\Services\Admin\Dashboard\DashboardPerson::fromPayload($row['person'])" size="md" decorative />
                                                @endif
                                                <span class="min-w-0 flex-1">
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
                        </x-admin.tab-panel>
                    @endforeach
                </x-admin.tabs>
            </x-admin.card>
        @endif

        @if ($moreTiles->isNotEmpty())
            <x-admin.card :title="__('admin.home.sections.more')" icon="star" class="md:col-span-2 {{ $stats['recent'] !== [] ? 'xl:col-span-4' : 'xl:col-span-12' }}">
                <dl class="{{ $stats['recent'] === [] ? 'grid gap-x-8 sm:grid-cols-2 xl:grid-cols-4' : 'divide-y divide-ink-200' }}">
                    @foreach ($moreTiles as $tile)
                        <div class="flex items-center justify-between gap-3 py-2">
                            <dt class="min-w-0 text-sm text-ink-700">
                                @if ($tile['href'] ?? null)<a href="{{ $tile['href'] }}" class="hover:text-coral-700">{{ __('admin.home.tiles.' . $tile['key']) }}</a>@else{{ __('admin.home.tiles.' . $tile['key']) }}@endif
                            </dt>
                            <dd class="flex shrink-0 items-center gap-2">
                                @if (array_key_exists('change', $tile))<x-admin.trend :change="$tile['change']" />@endif
                                <span dir="ltr" class="min-w-12 text-end text-sm font-bold">{{ $home->format($tile) }}</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-admin.card>
        @endif
    </div>
@endif
