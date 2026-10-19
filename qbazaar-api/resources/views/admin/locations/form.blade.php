@extends('admin.layout')

@php($editing = $location->exists)

@section('title', $editing ? __('admin.catalog.locations.edit_title') : __('admin.catalog.locations.new'))
@section('heading', $editing ? __('admin.catalog.locations.edit_title') : __('admin.catalog.locations.new'))

@section('content')
    <x-admin.page
        :title="$editing ? __('admin.catalog.locations.edit_heading') : __('admin.catalog.locations.new')"
        :crumbs="array_filter([
            [__('admin.catalog.locations.title'), route('admin.locations.index')],
            $editing ? $location->getLocalizedName('ar') : null,
            $editing ? __('admin.catalog.edit') : __('admin.catalog.locations.new'),
        ])"
        :form="$editing ? [route('admin.locations.update', $location), 'PUT'] : [route('admin.locations.store')]"
        :cancel="route('admin.locations.index')"
        :submit="$editing ? __('admin.catalog.save_changes') : __('admin.catalog.create')"
    >
        @if ($editing)
            <x-slot:badges>
                @if ($location->type)
                    <x-admin.badge>{{ $location->type->label()['ar'] }}</x-admin.badge>
                @endif
                <x-admin.badge :tone="$location->is_active ? 'success' : 'neutral'">{{ $location->is_active ? __('admin.catalog.status.active') : __('admin.catalog.status.inactive') }}</x-admin.badge>
            </x-slot:badges>

            <x-slot:menu>
                <x-admin.menu.item :icon="$location->is_active ? 'ban' : 'check'" :action="route('admin.locations.status', $location)" method="PATCH"
                    :fields="['is_active' => $location->is_active ? '0' : '1']"
                    :confirm="$location->is_active ? __('admin.catalog.locations.confirm_deactivate') : null">
                    {{ $location->is_active ? __('admin.catalog.locations.deactivate') : __('admin.catalog.locations.activate') }}
                </x-admin.menu.item>
                <x-admin.menu.divider />
                <x-admin.menu.item danger icon="trash" :action="route('admin.locations.destroy', $location)" method="DELETE" :confirm="__('admin.catalog.locations.confirm_delete')">
                    {{ __('admin.catalog.locations.delete') }}
                </x-admin.menu.item>
            </x-slot:menu>
        @endif

        @if ($blocked)
            <x-slot:lead>
                @include('admin.catalog.delete-blocked', ['notice' => $blocked])
            </x-slot:lead>
        @endif

        <x-admin.form-section :title="__('admin.catalog.sections.names')" :description="__('admin.catalog.sections.names_hint')" cols="2">
            <x-admin.input name="name[ar]" :label="__('admin.catalog.attributes.name_ar')" :value="old('name.ar', $location->name['ar'] ?? '')" required />
            <x-admin.input name="name[en]" :label="__('admin.catalog.attributes.name_en')" :value="old('name.en', $location->name['en'] ?? '')" dir="ltr" required />
        </x-admin.form-section>

        <x-admin.form-section :title="__('admin.catalog.locations.coordinates')" :description="__('admin.catalog.locations.coordinates_hint')" cols="2">
            <x-admin.input name="lat" type="number" :label="__('admin.catalog.attributes.lat')" :value="old('lat', $location->lat)" step="0.000001" min="-90" max="90" dir="ltr" />
            <x-admin.input name="lng" type="number" :label="__('admin.catalog.attributes.lng')" :value="old('lng', $location->lng)" step="0.000001" min="-180" max="180" dir="ltr" />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card :title="__('admin.catalog.sections.organisation')">
                <div class="grid gap-4">
                    <x-admin.select name="type" :label="__('admin.catalog.attributes.type')" required>
                        @foreach ($types as $case)
                            <option value="{{ $case->value }}" @selected(old('type', $location->type?->value) === $case->value)>{{ $case->label()['ar'] }}</option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.select name="parent_id" :label="__('admin.catalog.attributes.parent_location')">
                        <option value="">{{ __('admin.catalog.no_parent') }}</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent['id'] }}" @selected(old('parent_id', $location->parent_id) === $parent['id'])>
                                {{ str_repeat('— ', $parent['depth']) . $parent['label'] }}
                            </option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.input name="slug" :label="__('admin.catalog.attributes.slug')" :value="old('slug', $location->slug)" dir="ltr" required
                        :hint="__('admin.catalog.locations.slug_hint')" />
                    <x-admin.input name="order" type="number" :label="__('admin.catalog.attributes.order')" :value="old('order', $location->order ?? 0)" min="0" required />
                    <x-admin.checkbox name="is_active" :label="__('admin.catalog.status.active')" :checked="old('is_active', $editing ? $location->is_active : true)" />
                    <p class="text-xs text-ink-500">{{ __('admin.catalog.locations.active_hint') }}</p>
                </div>
            </x-admin.side-card>

            @if ($editing)
                <x-admin.side-card :title="__('admin.catalog.locations.details_card')">
                    <x-admin.meta-list>
                        @if ($usage)
                            <x-admin.meta-item :label="__('admin.catalog.locations.children')">{{ number_format($usage->children) }}</x-admin.meta-item>
                            <x-admin.meta-item :label="__('admin.catalog.linked_ads')">{{ number_format($usage->ads) }}</x-admin.meta-item>
                            <x-admin.meta-item :label="__('admin.catalog.saved_searches')">{{ number_format($usage->savedSearches) }}</x-admin.meta-item>
                            <x-admin.meta-item :label="__('admin.catalog.locations.addresses')">{{ number_format($usage->addresses) }}</x-admin.meta-item>
                        @endif
                        <x-admin.meta-item :label="__('admin.catalog.internal_id')" ltr>{{ $location->id }}</x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.catalog.created_at')" ltr>{{ $location->created_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.catalog.updated_at')" ltr>{{ $location->updated_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                    </x-admin.meta-list>
                    @if ($usage?->inUse())
                        <p class="mt-3 border-t border-ink-200 pt-3 text-xs text-ink-500">{{ __('admin.catalog.delete.in_use_hint', ['usage' => $usage->summary($kind)]) }}</p>
                    @endif
                    @unless ($usage)
                        <x-admin.error-state class="mt-3 border-t border-ink-200" :retry-url="request()->fullUrl()" />
                    @endunless
                </x-admin.side-card>
            @endif
        </x-slot:side>
    </x-admin.page>
@endsection
