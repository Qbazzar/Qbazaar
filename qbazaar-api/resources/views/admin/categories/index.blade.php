@extends('admin.layout')

@section('title', __('admin.catalog.categories.title'))
@section('heading', __('admin.catalog.categories.title'))

@section('content')
    @include('admin.catalog.delete-blocked', ['notice' => $blocked])

    <x-admin.page-toolbar :description="$search === '' ? __('admin.catalog.categories.tree_hint') : null">
        <x-admin.filter-bar :reset-url="route('admin.categories.index')" :search="$search" :placeholder="__('admin.catalog.categories.search_placeholder')" />
        <x-slot:actions>
            <x-admin.button :href="route('admin.categories.create')" icon="plus">{{ __('admin.catalog.categories.new') }}</x-admin.button>
        </x-slot:actions>
    </x-admin.page-toolbar>

    <x-admin.bulk-bar :action="route('admin.categories.bulk-destroy')" :label="__('admin.catalog.bulk.delete')" :confirm="__('admin.catalog.bulk.confirm')" />

    <x-admin.table
        :caption="__('admin.catalog.categories.title')"
        selectable
        :columns="[
            __('admin.catalog.columns.name'),
            ['label' => __('admin.catalog.columns.slug'), 'secondary' => true],
            __('admin.catalog.columns.fields'),
            __('admin.catalog.columns.ads'),
            __('admin.catalog.columns.status'),
            ['label' => __('admin.catalog.columns.actions'), 'srOnly' => true],
        ]"
        :empty="$rows->isEmpty()"
        empty-icon="folder"
        :empty-message="__('admin.catalog.categories.empty')"
        data-qb-category-tree
    >
        @foreach ($rows as $row)
            <x-admin.table.row data-depth="{{ $row->depth }}">
                <x-admin.table.select :value="$row->id" :label="__('admin.catalog.select_row', ['name' => $row->name])" />
                <x-admin.table.cell primary>
                    <div class="flex min-w-0 items-center gap-2" style="padding-inline-start: {{ $row->depth * 1.5 }}rem">
                        @if ($row->depth > 0)
                            <span class="shrink-0 text-ink-300" aria-hidden="true">└</span>
                        @endif
                        <a href="{{ route('admin.categories.edit', $row->id) }}" class="truncate font-semibold hover:text-coral-700">{{ $row->name }}</a>
                    </div>
                    @if ($row->parentPath)
                        <p class="mt-0.5 text-xs text-ink-500">{{ __('admin.catalog.categories.under', ['path' => $row->parentPath]) }}</p>
                    @endif
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.catalog.columns.slug')" secondary class="text-ink-500"><span dir="ltr">{{ $row->slug }}</span></x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.catalog.columns.fields')">
                    <a href="{{ route('admin.categories.fields.index', $row->id) }}" class="font-semibold text-ink-700 hover:text-coral-700">
                        {{ trans_choice('admin.catalog.categories.fields_count', $row->totalFields, ['count' => number_format($row->totalFields)]) }}
                    </a>
                    @if ($row->inheritedFields() > 0)
                        <span class="block text-xs text-ink-500">{{ trans_choice('admin.catalog.categories.inherited_count', $row->inheritedFields(), ['count' => number_format($row->inheritedFields())]) }}</span>
                    @endif
                </x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.catalog.columns.ads')" class="text-ink-700">{{ number_format($row->usage->ads) }}</x-admin.table.cell>
                <x-admin.table.cell :label="__('admin.catalog.columns.status')">
                    <x-admin.badge :tone="$row->statusTone()">{{ $row->statusLabel() }}</x-admin.badge>
                </x-admin.table.cell>
                <x-admin.table.actions>
                    <x-admin.icon-button :href="route('admin.categories.fields.index', $row->id)" icon="adjustments" :label="__('admin.catalog.categories.manage_fields')" />
                    <x-admin.icon-button :href="route('admin.categories.edit', $row->id)" icon="pencil" :label="__('admin.catalog.edit')" />
                    <form method="POST" action="{{ route('admin.categories.status', $row->id) }}"
                          @if ($row->active) data-confirm="{{ __('admin.catalog.categories.confirm_deactivate') }}" @endif>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $row->active ? '0' : '1' }}">
                        <x-admin.icon-button :icon="$row->active ? 'ban' : 'check'" :label="$row->active ? __('admin.catalog.deactivate') : __('admin.catalog.activate')" />
                    </form>
                    <form method="POST" action="{{ route('admin.categories.destroy', $row->id) }}" data-confirm="{{ __('admin.catalog.categories.confirm_delete') }}">
                        @csrf
                        @method('DELETE')
                        <x-admin.icon-button icon="trash" :label="__('admin.catalog.delete.action')" variant="danger" />
                    </form>
                </x-admin.table.actions>
            </x-admin.table.row>
        @endforeach
    </x-admin.table>

    <x-admin.pagination :paginator="$rows" />
@endsection
