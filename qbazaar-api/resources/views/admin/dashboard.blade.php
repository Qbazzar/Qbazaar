@extends('admin.layout')

@section('title', __('admin.dashboard.title'))
@section('heading', __('admin.dashboard.title'))

@section('content')
    @php
        $locale = app()->getLocale();
        $localized = fn (?array $names): string => $names[$locale] ?? $names['en'] ?? '—';
        $ago = fn (?string $time): string => $time ? \Illuminate\Support\Carbon::parse($time)->diffForHumans() : '—';
        $generatedAt = \Illuminate\Support\Carbon::parse($stats['generated_at']);
        $tiles = collect($stats['groups'])->flatMap(fn (array $group): array => $group['tiles'])->keyBy('key');
        $series = $stats['series'];
        $distributions = $stats['distributions'];
        $showMoney = fn (array $tile): string => match ($tile['kind']) {
            'money' => \App\Support\Money::format((string) $tile['value']),
            'percent' => $tile['value'] . '%',
            default => number_format($tile['value']),
        };
        $adsTotal = array_sum($distributions['ads'] ?? []);
    $kpiDefs = [
        ['tile' => 'users_total', 'delta' => 'users_new', 'series' => 'users', 'tone' => 'blue', 'icon' => 'users'],
        ['tile' => 'ads_active', 'delta' => 'ads_new', 'series' => 'ads', 'tone' => 'coral', 'icon' => 'tag'],
        ['tile' => 'orders_total', 'series' => 'orders', 'tone' => 'violet', 'icon' => 'banknotes'],
        ['tile' => 'orders_gmv', 'series' => 'revenue', 'field' => 'gmv', 'tone' => 'violet', 'icon' => 'banknotes'],
        ['tile' => 'commission_earned', 'series' => 'revenue', 'field' => 'commission', 'tone' => 'aqua', 'icon' => 'banknotes'],
        ['tile' => 'messages_new', 'series' => 'messages', 'tone' => 'slate', 'icon' => 'chat'],
        ['tile' => 'reports_new', 'tone' => 'slate', 'icon' => 'flag'],
        ['tile' => 'tickets_new', 'tone' => 'slate', 'icon' => 'lifebuoy'],
    ];
    $kpis = array_slice(array_values(array_filter($kpiDefs, fn (array $def): bool => $tiles->has($def['tile']))), 0, 6);
    $shownTiles = collect($kpis)->flatMap(fn (array $def): array => array_filter([$def['tile'], $def['delta'] ?? null]))->all();

    $stockDefs = [
        ['tile' => 'ads_pending', 'of' => 'ads', 'tone' => 'warning', 'icon' => 'tag'],
        ['tile' => 'ads_expired', 'of' => 'ads', 'tone' => 'muted', 'icon' => 'tag'],
        ['tile' => 'ads_rejected', 'of' => 'ads', 'tone' => 'danger', 'icon' => 'tag'],
        ['tile' => 'users_verified', 'of' => 'percent', 'tone' => 'success', 'icon' => 'users'],
        ['tile' => 'users_active', 'of' => 'users', 'tone' => 'blue', 'icon' => 'users'],
        ['tile' => 'users_business', 'of' => 'users', 'tone' => 'violet', 'icon' => 'users'],
    ];
    $usersTotal = $tiles->get('users_total')['value'] ?? 0;
    $totals = ['ads' => $adsTotal, 'users' => $usersTotal, 'percent' => 100];
    $stock = array_values(array_filter($stockDefs, fn (array $def): bool => $tiles->has($def['tile'])));
    $shownTiles = array_merge($shownTiles, array_column($stock, 'tile'));
    @endphp

    {{-- Chart colours and tooltip styling are scoped to this page. --}}
    <style>
        .dash { --viz-track: #f1f2f5; }
    </style>

    <div class="dash flex w-full flex-col gap-4">
        {{-- Top bar --}}
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <h2 class="text-lg font-bold">{{ __('admin.home.heading') }}</h2>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <p class="text-xs text-ink-500">
                    {{ __('admin.home.updated') }}:
                    <time datetime="{{ $generatedAt->toIso8601String() }}" title="{{ $generatedAt->toDateTimeString() }}">{{ $generatedAt->diffForHumans() }}</time>
                </p>
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
            </div>
        </div>

        @include('admin.dashboard.kpis')
        @include('admin.dashboard.attention')
        @include('admin.dashboard.charts')
        @include('admin.dashboard.breakdowns')
        @include('admin.dashboard.activity')
        @include('admin.dashboard.health')
    </div>
@endsection
