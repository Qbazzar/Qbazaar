@extends('admin.layout')

@section('title', __('admin.review_queue.title'))
@section('heading', __('admin.review_queue.title'))

@section('content')
    <x-admin.page-toolbar :description="__('admin.review_queue.subtitle', ['hours' => $summary->targetHours])" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-admin.stat :label="__('admin.review_queue.stats.waiting')" icon="inbox" :value="number_format($summary->waiting)" />
        <x-admin.stat :label="__('admin.review_queue.stats.overdue')" icon="exclamation">
            <span @class(['tabular-nums', 'text-danger-700' => $summary->overdue > 0])>{{ number_format($summary->overdue) }}</span>
        </x-admin.stat>
        <x-admin.stat :label="__('admin.review_queue.stats.oldest')" icon="clock">
            @if ($summary->oldestWaited !== null)
                <span class="flex flex-wrap items-center gap-2">
                    <span>{{ $summary->oldestWaited }}</span>
                    <x-admin.badge :tone="$summary->oldestState->tone()">{{ $summary->oldestState->label() }}</x-admin.badge>
                </span>
            @else
                {{ __('admin.review_queue.stats.none') }}
            @endif
        </x-admin.stat>
    </div>

    {{-- Approve and reject share one selection: approve posts to its own route and needs no reason. --}}
    <x-admin.bulk-bar :action="route('admin.moderation.queue.reject')" :confirm="__('admin.review_queue.bulk.confirm')" variant="primary">
        <x-slot:buttons>
            <x-admin.button variant="success" icon="check" :formaction="route('admin.moderation.queue.approve')" formnovalidate>{{ __('admin.review_queue.bulk.approve') }}</x-admin.button>
            @can('ads.reject')
                <x-admin.button variant="danger" icon="x-circle">{{ __('admin.review_queue.bulk.reject') }}</x-admin.button>
            @endcan
        </x-slot:buttons>
        @can('ads.reject')
            <x-admin.sanction-fields prefix="queue-reject" :required="false" :hint="__('admin.review_queue.bulk.reason_hint')" class="md:grid-cols-2" />
        @endcan
    </x-admin.bulk-bar>

    <x-admin.table
        :caption="__('admin.review_queue.title')"
        selectable
        :columns="[
            __('admin.review_queue.columns.ad'),
            __('admin.review_queue.columns.seller'),
            __('admin.review_queue.columns.waited'),
            __('admin.review_queue.columns.sla'),
            ['label' => __('admin.review_queue.columns.checks'), 'secondary' => true],
            ['label' => __('admin.review_queue.columns.actions'), 'srOnly' => true],
        ]"
        :empty="$rows->isEmpty()"
        empty-icon="check"
        :empty-message="__('admin.review_queue.empty')"
    >
        @foreach ($rows as $row)
            <x-admin.table.row :data-sla="$row->sla->value" @class(['bg-danger-50/60' => $row->isOverdue()])>
                <x-admin.table.select :value="$row->ad->id" :label="__('admin.review_queue.select', ['title' => $row->ad->title])" />
                <x-admin.table.cell primary>
                    <div class="flex min-w-0 items-center gap-3">
                        <x-admin.ad-thumb :ad="$row->ad" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.ads.show', $row->ad) }}" title="{{ $row->ad->title }}" class="block max-w-xs truncate font-semibold hover:text-coral-700 lg:max-w-sm">{{ $row->ad->title }}</a>
                            @if ($row->ad->category)
                                <span class="block truncate text-xs text-ink-500">{{ $row->ad->category->getLocalizedName(app()->getLocale()) }}</span>
                            @endif
                        </div>
                    </div>
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.review_queue.columns.seller')" class="max-w-48 text-ink-700"><x-admin.user :user="$row->ad->user" /></x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.review_queue.columns.waited')" class="text-ink-700">
                    <time datetime="{{ $row->since->toIso8601String() }}" title="{{ $row->since->format('Y-m-d H:i') }}">{{ $row->waited }}</time>
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.review_queue.columns.sla')"><x-admin.badge :tone="$row->sla->tone()">{{ $row->sla->label() }}</x-admin.badge></x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.review_queue.columns.checks')" secondary>
                    @if ($row->clean === null)
                        <x-admin.badge>{{ __('admin.review_queue.checks.pending') }}</x-admin.badge>
                    @elseif ($row->clean)
                        <x-admin.badge tone="success">{{ __('admin.review_queue.checks.clean') }}</x-admin.badge>
                    @else
                        <span class="flex flex-wrap gap-1">
                            @foreach ($row->flags as $flag)
                                <x-admin.badge tone="warning">{{ $flag }}</x-admin.badge>
                            @endforeach
                        </span>
                    @endif
                </x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.ads.show', $row->ad)" icon="eye" :label="__('admin.review_queue.open')" />
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$rows" />
@endsection
