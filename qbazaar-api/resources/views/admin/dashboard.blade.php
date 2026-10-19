@extends('admin.layout')

@section('title', __('admin.dashboard.title'))
@section('heading', __('admin.dashboard.title'))

@section('content')
    @php
        $locale = app()->getLocale();
        $localized = fn (?array $names): string => $names[$locale] ?? $names['en'] ?? '—';
        $ago = fn (?string $time): string => $time ? \Illuminate\Support\Carbon::parse($time)->diffForHumans() : '—';
        $generatedAt = \Illuminate\Support\Carbon::parse($stats['generated_at']);
        $series = $stats['series'];
        $distributions = $stats['distributions'];
    @endphp

    <div class="flex w-full flex-col gap-4">
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
        @include('admin.dashboard.engagement')
        @include('admin.dashboard.breakdowns')
        @include('admin.dashboard.activity')
        @include('admin.dashboard.health')
    </div>
@endsection
