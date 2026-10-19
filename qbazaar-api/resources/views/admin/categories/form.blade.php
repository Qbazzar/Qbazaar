@extends('admin.layout')

@php($editing = $category->exists)

@section('title', $editing ? __('admin.catalog.categories.edit_title') : __('admin.catalog.categories.new'))
@section('heading', $editing ? __('admin.catalog.categories.edit_title') : __('admin.catalog.categories.new'))

@section('content')
    <x-admin.page
        :title="$editing ? __('admin.catalog.categories.edit_heading') : __('admin.catalog.categories.new')"
        :crumbs="array_filter([
            [__('admin.catalog.categories.title'), route('admin.categories.index')],
            $editing ? $category->getLocalizedName('ar') : null,
            $editing ? __('admin.catalog.edit') : __('admin.catalog.categories.new'),
        ])"
        :form="$editing ? [route('admin.categories.update', $category), 'PUT'] : [route('admin.categories.store')]"
        :cancel="route('admin.categories.index')"
        :submit="$editing ? __('admin.catalog.save_changes') : __('admin.catalog.create')"
    >
        @if ($editing)
            <x-slot:badges>
                <x-admin.badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? __('admin.catalog.status.active') : __('admin.catalog.status.inactive') }}</x-admin.badge>
            </x-slot:badges>

            <x-slot:actions>
                <x-admin.button variant="secondary" :href="route('admin.categories.fields.index', $category)" icon="adjustments" class="max-sm:hidden">{{ __('admin.catalog.categories.manage_fields') }}</x-admin.button>
            </x-slot:actions>

            <x-slot:menu>
                @if ($category->is_active)
                    <x-admin.menu.item :href="rtrim((string) config('qbazaar.web_url'), '/') . '/c/' . $category->slug" icon="external" target="_blank" rel="noopener">
                        {{ __('admin.catalog.categories.view_on_site') }} <span class="sr-only">{{ __('admin.shell.opens_new_tab') }}</span>
                    </x-admin.menu.item>
                    <x-admin.menu.divider />
                @endif
                <x-admin.menu.item icon="adjustments" :href="route('admin.categories.fields.index', $category)">
                    {{ __('admin.catalog.categories.manage_fields') }}
                </x-admin.menu.item>
                <x-admin.menu.item :icon="$category->is_active ? 'ban' : 'check'" :action="route('admin.categories.status', $category)" method="PATCH"
                    :fields="['is_active' => $category->is_active ? '0' : '1']"
                    :confirm="$category->is_active ? __('admin.catalog.categories.confirm_deactivate') : null">
                    {{ $category->is_active ? __('admin.catalog.categories.deactivate') : __('admin.catalog.categories.activate') }}
                </x-admin.menu.item>
                <x-admin.menu.divider />
                <x-admin.menu.item danger icon="trash" :action="route('admin.categories.destroy', $category)" method="DELETE" :confirm="__('admin.catalog.categories.confirm_delete')">
                    {{ __('admin.catalog.categories.delete') }}
                </x-admin.menu.item>
            </x-slot:menu>
        @endif

        @if ($blocked)
            <x-slot:lead>
                @include('admin.catalog.delete-blocked', ['notice' => $blocked])
            </x-slot:lead>
        @endif

        <x-admin.form-section :title="__('admin.catalog.sections.names')" :description="__('admin.catalog.sections.names_hint')" cols="2">
            <x-admin.input name="name[ar]" :label="__('admin.catalog.attributes.name_ar')" :value="old('name.ar', $category->name['ar'] ?? '')" required />
            <x-admin.input name="name[en]" :label="__('admin.catalog.attributes.name_en')" :value="old('name.en', $category->name['en'] ?? '')" dir="ltr" required />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card :title="__('admin.catalog.sections.organisation')">
                <div class="grid gap-4">
                    <x-admin.select name="parent_id" :label="__('admin.catalog.attributes.parent_category')" :hint="__('admin.catalog.categories.parent_hint')">
                        <option value="">{{ __('admin.catalog.no_parent') }}</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent['id'] }}" @selected(old('parent_id', $category->parent_id) === $parent['id'])>
                                {{ str_repeat('— ', $parent['depth']) . $parent['label'] }}
                            </option>
                        @endforeach
                    </x-admin.select>
                    <x-admin.input name="slug" :label="__('admin.catalog.attributes.slug')" :value="old('slug', $category->slug)" dir="ltr" required
                        :hint="__('admin.catalog.categories.slug_hint')" />
                    <x-admin.input name="icon" :label="__('admin.catalog.attributes.icon')" :value="old('icon', $category->icon)" dir="ltr" />
                    <x-admin.input name="order" type="number" :label="__('admin.catalog.attributes.order')" :value="old('order', $category->order ?? 0)" min="0" required />
                    <x-admin.checkbox name="is_active" :label="__('admin.catalog.status.active')" :checked="old('is_active', $editing ? $category->is_active : true)" />
                </div>
            </x-admin.side-card>

            @if ($editing)
                <x-admin.side-card :title="__('admin.catalog.categories.fields_card')" icon="adjustments" :link="[__('admin.catalog.categories.manage_fields'), route('admin.categories.fields.index', $category)]">
                    <x-admin.meta-list>
                        <x-admin.meta-item :label="__('admin.catalog.categories.own_fields')">{{ number_format($schema->countDeclaredBy($category->id)) }}</x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.catalog.categories.inherited_fields')">{{ number_format($schema->count() - $schema->countDeclaredBy($category->id)) }}</x-admin.meta-item>
                    </x-admin.meta-list>
                </x-admin.side-card>

                <x-admin.side-card :title="__('admin.catalog.categories.details_card')">
                    <x-admin.meta-list>
                        @if ($usage)
                            <x-admin.meta-item :label="__('admin.catalog.categories.children')">{{ number_format($usage->children) }}</x-admin.meta-item>
                            <x-admin.meta-item :label="__('admin.catalog.linked_ads')">{{ number_format($usage->ads) }}</x-admin.meta-item>
                            <x-admin.meta-item :label="__('admin.catalog.saved_searches')">{{ number_format($usage->savedSearches) }}</x-admin.meta-item>
                        @endif
                        <x-admin.meta-item :label="__('admin.catalog.internal_id')" ltr>{{ $category->id }}</x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.catalog.created_at')" ltr>{{ $category->created_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
                        <x-admin.meta-item :label="__('admin.catalog.updated_at')" ltr>{{ $category->updated_at?->format('Y-m-d H:i') }}</x-admin.meta-item>
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
