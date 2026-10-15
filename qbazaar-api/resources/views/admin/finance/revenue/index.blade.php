@extends('admin.layout')

@section('title', __('admin.revenue.title'))
@section('heading', __('admin.revenue.title'))

@section('content')
    <x-admin.page :title="__('admin.revenue.title')" :subtitle="$revenue->window()->label() . ' · ' . __('admin.revenue.updated', ['time' => $revenue->generatedAt()])" side-label="{{ __('admin.revenue.liabilities.title') }}">
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="arrow-right" :href="$revenue->exportUrl($revenue->periodQuery())">{{ __('admin.revenue.export') }}</x-admin.button>
        </x-slot:actions>

        <x-slot:lead>
            @include('admin.finance.revenue.period')
        </x-slot:lead>

        @if ($errors->any())
            <x-admin.alert tone="danger" :title="__('admin.shell.error_summary')" :summary="$errors" />
        @endif

        @include('admin.finance.revenue.kpis')

        @if ($revenue->isEmpty())
            <x-admin.card>
                <x-admin.empty-state icon="banknotes" :message="__('admin.revenue.empty')" />
            </x-admin.card>
        @endif

        @include('admin.finance.revenue.charts')
        @include('admin.finance.revenue.statement')
        @include('admin.finance.revenue.tables')

        <x-slot:side>
            <x-admin.side-card :title="__('admin.revenue.liabilities.title')" icon="shield" id="side-liabilities">
                <p class="mb-3 text-xs text-ink-500">{{ __('admin.revenue.liabilities.note') }}</p>
                <x-admin.meta-list>
                    @foreach ($revenue->liabilities() as $row)
                        <x-admin.meta-item :label="$row['label']" ltr>{{ $row['value'] }}</x-admin.meta-item>
                    @endforeach
                </x-admin.meta-list>
            </x-admin.side-card>

            <x-admin.side-card :title="__('admin.revenue.related')" icon="banknotes" id="side-related">
                <ul class="grid gap-2">
                    @can('finance.manage')
                        <li><a class="font-semibold text-coral-700 hover:underline" href="{{ route('admin.finance.settlements.index') }}">{{ __('admin.revenue.links.settlements') }}</a></li>
                        <li><a class="font-semibold text-coral-700 hover:underline" href="{{ route('admin.finance.withdrawals.index') }}">{{ __('admin.revenue.links.withdrawals') }}</a></li>
                        <li><a class="font-semibold text-coral-700 hover:underline" href="{{ route('admin.finance.promotions.index') }}">{{ __('admin.revenue.links.promotions') }}</a></li>
                    @endcan
                    <li><a class="font-semibold text-coral-700 hover:underline" href="{{ route('admin.finance.disputes.index') }}">{{ __('admin.revenue.links.disputes') }}</a></li>
                </ul>
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
