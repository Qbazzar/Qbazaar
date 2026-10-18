@extends('admin.layout')

@section('title', __('admin.companies.featured.page_title'))
@section('heading', __('admin.companies.title'))

@section('content')
    <x-admin.page :title="__('admin.companies.featured.page_title')"
                  :subtitle="__('admin.companies.featured.page_subtitle', ['count' => $companies->count(), 'max' => $max])"
                  :crumbs="[[__('admin.companies.title'), route('admin.companies.index')], __('admin.companies.featured.page_title')]"
                  :form="$canFeature && $companies->isNotEmpty() ? ['action' => route('admin.companies.featured.reorder'), 'method' => 'PUT', 'id' => 'featured-order'] : null"
                  :submit="__('admin.companies.featured.save_order')"
                  width="form">
        <x-admin.form-section :title="__('admin.companies.featured.list_title')" icon="star" id="featured-list"
                              :description="__('admin.companies.featured.list_hint')">
            @if ($companies->isEmpty())
                <x-admin.empty-state compact icon="star" :message="__('admin.companies.featured.empty')" />
            @else
                <ol class="grid gap-3">
                    @foreach ($companies as $company)
                        <li class="flex flex-wrap items-center gap-3 rounded-xl border border-ink-200 bg-cream-50 p-3">
                            @if ($canFeature)
                                <x-admin.input type="number" :name="'positions[' . $company->id . ']'" :id="'position-' . $company->id"
                                               :value="old('positions.' . $company->id, $company->businessProfile?->featured_position)"
                                               :label="__('admin.companies.featured.position_label', ['name' => $company->businessProfile?->business_name ?: $company->full_name])"
                                               label-hidden min="1" max="999" class="w-20" input-class="text-center" />
                            @else
                                <span class="inline-flex size-9 items-center justify-center rounded-full bg-coral-soft font-bold text-coral-700 tabular-nums">{{ $company->businessProfile?->featured_position }}</span>
                            @endif
                            <x-admin.user-avatar :user="$company" size="md" decorative />
                            <span class="min-w-0 flex-1">
                                <a href="{{ route('admin.companies.show', $company) }}" class="block truncate font-semibold text-ink-900 hover:text-coral-700">{{ $company->businessProfile?->business_name ?: $company->full_name }}</a>
                                <span class="block text-xs text-ink-500">{{ trans_choice('admin.companies.featured.ads_count', $company->active_ads_count, ['count' => $company->active_ads_count]) }}</span>
                            </span>
                            <x-admin.badge :status="$company->status" />
                            @if ($canFeature)
                                <x-admin.form-button :action="route('admin.companies.unfeature', $company)" variant="danger-ghost" size="sm" icon="x-circle"
                                                     :confirm="__('admin.companies.featured.remove_confirm')">{{ __('admin.companies.featured.remove') }}</x-admin.form-button>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-admin.form-section>
    </x-admin.page>
@endsection
