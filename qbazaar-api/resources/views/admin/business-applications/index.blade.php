@extends('admin.layout')

@section('title', __('admin.business_applications.title'))
@section('heading', __('admin.business_applications.title'))

@section('content')
    <x-admin.page-toolbar :description="__('admin.business_applications.description')">
        <x-admin.status-tabs route="admin.business-applications.index" :statuses="$statuses" :active="$status"
                             :label="__('admin.business_applications.status_filter')" :params="array_filter(['q' => $search])" />
        <x-slot:actions>
            <x-admin.filter-bar
                :reset-url="route('admin.business-applications.index', ['status' => $status->value])"
                :search="$search"
                :placeholder="__('admin.business_applications.search_placeholder')"
                :carry="['status' => $status->value]"
            />
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.table
        :caption="__('admin.business_applications.title')"
        :columns="[
            __('admin.business_applications.columns.business'),
            __('admin.business_applications.columns.applicant'),
            ['label' => __('admin.business_applications.columns.registration'), 'secondary' => true],
            __('admin.business_applications.columns.documents'),
            __('admin.business_applications.columns.submitted'),
            __('admin.business_applications.columns.status'),
            ['label' => __('admin.business_applications.columns.actions'), 'srOnly' => true],
        ]"
        :empty="$applications->isEmpty()"
        empty-icon="inbox"
        :empty-message="__('admin.business_applications.empty')"
    >
        @foreach ($applications as $application)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.business-applications.show', $application) }}" class="block truncate font-semibold text-ink-900 hover:text-coral-700">{{ $application->business_name }}</a>
                    <span class="block truncate text-xs text-ink-500">{{ $application->legal_name }}</span>
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.business_applications.columns.applicant')">
                    <x-admin.user :user="$application->user" :fallback="__('admin.business_applications.deleted_account')" />
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.business_applications.columns.registration')" secondary class="text-ink-700"><span dir="ltr">{{ $application->commercial_registration_number }}</span></x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.business_applications.columns.documents')" class="tabular-nums text-ink-700">{{ $application->media_count }}</x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.business_applications.columns.submitted')" class="text-ink-500">
                    <span dir="ltr">{{ $application->submitted_at->format('Y-m-d H:i') }}</span>
                    @if ($application->reviewer)
                        <span class="block text-xs">{{ __('admin.business_applications.reviewed_by', ['name' => $application->reviewer->full_name ?: $application->reviewer->email]) }}</span>
                    @endif
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.business_applications.columns.status')"><x-admin.badge :status="$application->status" /></x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.business-applications.show', $application)" icon="eye" :label="__('admin.business_applications.open')" />
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$applications" />
@endsection
