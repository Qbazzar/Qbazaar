@extends('admin.layout')

@section('title', __('admin.catalog.locations.title'))
@section('heading', __('admin.catalog.locations.title'))

@section('content')
    @include('admin.catalog.delete-blocked', ['notice' => $blocked])

    <x-admin.page-toolbar>
        <x-admin.filter-bar
            :reset-url="route('admin.locations.index')"
            :search="$search"
            :placeholder="__('admin.catalog.locations.search_placeholder')"
            :filters="[
                ['name' => 'type', 'label' => __('admin.catalog.attributes.type'), 'placeholder' => __('admin.catalog.locations.all_types'), 'value' => $type,
                    'options' => collect($types)->mapWithKeys(fn ($case) => [$case->value => $case->label()['ar']])],
                ['name' => 'status', 'label' => __('admin.catalog.columns.status'), 'placeholder' => __('admin.catalog.all_statuses'), 'value' => $status,
                    'options' => collect($statuses)->mapWithKeys(fn ($value) => [$value => __('admin.catalog.status.' . $value)])],
            ]"
        />
        <x-slot:actions>
            <x-admin.button :href="route('admin.locations.create')" icon="plus">{{ __('admin.catalog.locations.new') }}</x-admin.button>
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.locations.bulk-destroy')" :label="__('admin.catalog.bulk.delete')" :confirm="__('admin.catalog.bulk.confirm')" />

    <x-admin.table
        :caption="__('admin.catalog.locations.title')"
        selectable
        :columns="[
            __('admin.catalog.columns.name'),
            ['label' => __('admin.catalog.columns.slug'), 'secondary' => true],
            __('admin.catalog.attributes.type'),
            __('admin.catalog.attributes.parent_location'),
            ['label' => __('admin.catalog.attributes.order'), 'secondary' => true],
            __('admin.catalog.columns.status'),
            ['label' => __('admin.catalog.columns.actions'), 'srOnly' => true],
        ]"
        :empty="$locations->isEmpty()"
        empty-icon="map-pin"
        :empty-message="__('admin.catalog.locations.empty')"
    >
        @foreach ($locations as $location)
            @php($locationName = $location->getLocalizedName(app()->getLocale()))
            <x-admin.table.row>
                <x-admin.table.select :value="$location->id" :label="__('admin.catalog.select_row', ['name' => $locationName])" />
                <x-admin.table.cell primary>
                    <a href="{{ route('admin.locations.edit', $location) }}" class="font-semibold hover:text-coral-700">{{ $locationName }}</a>
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.catalog.columns.slug')" secondary class="text-ink-500"><span dir="ltr">{{ $location->slug }}</span></x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.catalog.attributes.type')"><x-admin.badge :status="$location->type" /></x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.catalog.attributes.parent_location')" class="text-ink-700">{{ $location->parent?->getLocalizedName(app()->getLocale()) ?? '—' }}</x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.catalog.attributes.order')" secondary class="text-ink-500">{{ $location->order }}</x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.catalog.columns.status')">
                    <x-admin.badge :tone="$location->is_active ? 'success' : 'neutral'">{{ $location->is_active ? __('admin.catalog.status.active') : __('admin.catalog.status.inactive') }}</x-admin.badge>
                </x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.locations.edit', $location)" icon="pencil" :label="__('admin.catalog.edit')" />
                    <form method="POST" action="{{ route('admin.locations.status', $location) }}"
                          @if ($location->is_active) data-confirm="{{ __('admin.catalog.locations.confirm_deactivate') }}" @endif>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $location->is_active ? '0' : '1' }}">
                        <x-admin.icon-button :icon="$location->is_active ? 'ban' : 'check'" :label="$location->is_active ? __('admin.catalog.deactivate') : __('admin.catalog.activate')" />
                    </form>
                    <form method="POST" action="{{ route('admin.locations.destroy', $location) }}" data-confirm="{{ __('admin.catalog.locations.confirm_delete') }}">
                        @csrf
                        @method('DELETE')
                        <x-admin.icon-button icon="trash" :label="__('admin.catalog.delete.action')" variant="danger" />
                    </form>
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$locations" />
@endsection
