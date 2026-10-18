@extends('admin.layout')

@section('title', __('admin.companies.title'))
@section('heading', __('admin.companies.title'))

@section('content')
    <x-admin.page-toolbar :description="__('admin.companies.description')">
        <x-admin.filter-bar
            :reset-url="route('admin.companies.index')"
            :search="$search"
            :placeholder="__('admin.companies.search_placeholder')"
            :filters="[
                ['name' => 'verification', 'label' => __('admin.companies.filters.label'), 'placeholder' => __('admin.companies.filters.all'),
                    'value' => $verification?->value, 'options' => $verificationOptions],
            ]"
        />
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="star" :href="route('admin.companies.featured.index')">{{ __('admin.companies.featured.nav') }}</x-admin.button>
            <x-admin.button variant="secondary" icon="document" :href="route('admin.business-applications.index')">
                {{ __('admin.business_applications.nav') }}
                @if ($pendingApplications > 0)
                    <span class="rounded-full bg-coral-600 px-2 py-0.5 text-[11px] font-bold text-white">{{ $pendingApplications }}<span class="sr-only"> {{ __('admin.business_applications.pending_badge') }}</span></span>
                @endif
            </x-admin.button>
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.table
        :caption="__('admin.companies.title')"
        :columns="[
            __('admin.companies.columns.company'),
            __('admin.companies.columns.verification'),
            __('admin.companies.columns.featured'),
            ['label' => __('admin.companies.columns.ads'), 'secondary' => true],
            ['label' => __('admin.companies.columns.followers'), 'secondary' => true],
            __('admin.companies.columns.status'),
            ['label' => __('admin.companies.columns.actions'), 'srOnly' => true],
        ]"
        :empty="$companies->isEmpty()"
        empty-icon="users"
        :empty-message="__('admin.companies.empty')"
    >
        @foreach ($companies as $company)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <span class="flex min-w-0 items-center gap-3">
                        <x-admin.user-avatar :user="$company" size="md" decorative />
                        <span class="min-w-0">
                            <a href="{{ route('admin.companies.show', $company) }}" class="block truncate font-semibold text-ink-900 hover:text-coral-700">{{ $company->businessProfile?->business_name ?: $company->full_name }}</a>
                            <span class="block truncate text-xs text-ink-500">{{ $company->full_name }}</span>
                        </span>
                    </span>
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.companies.columns.verification')">
                    @if ($company->business_verified_at)
                        <x-admin.badge tone="success">{{ __('admin.companies.verification.verified') }}</x-admin.badge>
                        <span class="mt-1 block text-xs text-ink-500">
                            <span dir="ltr">{{ $company->business_verified_at->format('Y-m-d') }}</span>
                            @if ($company->businessVerifier)
                                · {{ $company->businessVerifier->full_name ?: $company->businessVerifier->email }}
                            @endif
                        </span>
                    @else
                        <x-admin.badge>{{ __('admin.companies.verification.unverified') }}</x-admin.badge>
                    @endif
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.companies.columns.featured')">
                    @if ($company->businessProfile?->featured_position)
                        <x-admin.badge tone="brand">{{ __('admin.companies.featured.position', ['position' => $company->businessProfile->featured_position]) }}</x-admin.badge>
                    @else
                        <span class="text-ink-500">—</span>
                    @endif
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.companies.columns.ads')" secondary class="tabular-nums text-ink-700">{{ $company->active_ads_count }}</x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.companies.columns.followers')" secondary class="tabular-nums text-ink-700">{{ $company->followers_count }}</x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.companies.columns.status')"><x-admin.badge :status="$company->status" /></x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.companies.show', $company)" icon="eye" :label="__('admin.companies.open')" />
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$companies" />
@endsection
