@extends('admin.layout')

@section('title', __('admin.catalog.fields.title', ['name' => $category->getLocalizedName(app()->getLocale())]))
@section('heading', __('admin.catalog.categories.title'))

@section('content')
    <x-admin.page
        :title="__('admin.catalog.fields.title', ['name' => $category->getLocalizedName(app()->getLocale())])"
        :subtitle="__('admin.catalog.fields.subtitle')"
        :crumbs="[
            [__('admin.catalog.categories.title'), route('admin.categories.index')],
            ...array_map(fn (array $ancestor): array => [$ancestor['name'], route('admin.categories.edit', $ancestor['id'])], $view->ancestors),
            [$category->getLocalizedName(app()->getLocale()), route('admin.categories.edit', $category)],
            __('admin.catalog.fields.crumb'),
        ]"
    >
        <x-slot:actions>
            <x-admin.button :href="route('admin.categories.fields.create', $category)" icon="plus">{{ __('admin.catalog.fields.add') }}</x-admin.button>
        </x-slot:actions>

        <x-admin.table
            :caption="__('admin.catalog.fields.table_caption')"
            :columns="[
                __('admin.catalog.fields.columns.field'),
                __('admin.catalog.fields.columns.type'),
                __('admin.catalog.fields.columns.behaviour'),
                ['label' => __('admin.catalog.fields.columns.sort_order'), 'secondary' => true],
                __('admin.catalog.fields.columns.source'),
                ['label' => __('admin.catalog.columns.actions'), 'srOnly' => true],
            ]"
            :empty="$view->fields === []"
            empty-icon="adjustments"
            :empty-message="__('admin.catalog.fields.empty')"
            data-qb-category-fields
        >
            @foreach ($view->fields as $field)
                <x-admin.table.row data-field="{{ $field->key }}">
                    <x-admin.table.cell primary>
                        <span class="font-semibold">{{ $field->label('ar') }}</span>
                        <span class="block text-xs text-ink-500" dir="ltr">{{ $field->labelEn !== '' ? $field->labelEn . ' · ' : '' }}<code>{{ $field->key }}</code></span>
                    </x-admin.table.cell>
                    <x-admin.table.cell :label="__('admin.catalog.fields.columns.type')">
                        <x-admin.badge tone="info">{{ $field->type->label() }}</x-admin.badge>
                        @if ($field->optionCount > 0)
                            <span class="block text-xs text-ink-500">{{ trans_choice('admin.catalog.fields.option_count', $field->optionCount, ['count' => number_format($field->optionCount)]) }}</span>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.cell :label="__('admin.catalog.fields.columns.behaviour')">
                        <span class="flex flex-wrap gap-1">
                            @if ($field->required)
                                <x-admin.badge tone="brand">{{ __('admin.catalog.fields.flags.required') }}</x-admin.badge>
                            @endif
                            @if ($field->filterable)
                                <x-admin.badge tone="violet">{{ __('admin.catalog.fields.flags.filterable') }}</x-admin.badge>
                            @endif
                            @if ($field->showInCard)
                                <x-admin.badge>{{ __('admin.catalog.fields.flags.show_in_card') }}</x-admin.badge>
                            @endif
                            @unless ($field->required || $field->filterable || $field->showInCard)
                                <span class="text-ink-500">—</span>
                            @endunless
                        </span>
                    </x-admin.table.cell>
                    <x-admin.table.cell :label="__('admin.catalog.fields.columns.sort_order')" secondary class="text-ink-500">{{ $field->sortOrder }}</x-admin.table.cell>
                    <x-admin.table.cell :label="__('admin.catalog.fields.columns.source')">
                        <x-admin.badge :tone="$field->isOwn() ? 'success' : 'neutral'">{{ __('admin.catalog.fields.source.' . $field->source) }}</x-admin.badge>
                        @if ($field->sourceName)
                            <span class="block text-xs text-ink-500">{{ __('admin.catalog.fields.from', ['name' => $field->sourceName]) }}</span>
                        @endif
                    </x-admin.table.cell>
                    <x-admin.table.actions>
                        @if ($field->isOwn())
                            <x-admin.icon-button :href="route('admin.categories.fields.edit', [$category, $field->key])" icon="pencil" :label="__('admin.catalog.fields.edit', ['field' => $field->label('ar')])" />
                            <form method="POST" action="{{ route('admin.categories.fields.destroy', [$category, $field->key]) }}"
                                  data-confirm="{{ $field->source === 'override' ? __('admin.catalog.fields.confirm_remove_override') : __('admin.catalog.fields.confirm_remove') }}">
                                @csrf
                                @method('DELETE')
                                <x-admin.icon-button icon="trash" :label="__('admin.catalog.fields.remove', ['field' => $field->label('ar')])" variant="danger" />
                            </form>
                        @else
                            <x-admin.icon-button :href="route('admin.categories.fields.create', [$category, 'override' => $field->key])" icon="pencil" :label="__('admin.catalog.fields.override', ['field' => $field->label('ar')])" />
                            <form method="POST" action="{{ route('admin.categories.fields.hide', [$category, $field->key]) }}" data-confirm="{{ __('admin.catalog.fields.confirm_hide') }}">
                                @csrf
                                <x-admin.icon-button icon="ban" :label="__('admin.catalog.fields.hide', ['field' => $field->label('ar')])" />
                            </form>
                        @endif
                    </x-admin.table.actions>
                </x-admin.table.row>
            @endforeach
        </x-admin.table>

        @if ($view->hidden !== [])
            <x-admin.form-section :title="__('admin.catalog.fields.hidden_title')" :description="__('admin.catalog.fields.hidden_hint')" icon="eye">
                <ul class="divide-y divide-ink-200" data-qb-hidden-fields>
                    @foreach ($view->hidden as $hidden)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <span class="min-w-0">
                                <span class="font-semibold text-ink-900">{{ $hidden->label }}</span>
                                <code class="ms-1 text-xs text-ink-500" dir="ltr">{{ $hidden->key }}</code>
                                @if ($hidden->hiddenByName)
                                    <span class="block text-xs text-ink-500">{{ __('admin.catalog.fields.hidden_in', ['name' => $hidden->hiddenByName]) }}</span>
                                @endif
                            </span>
                            @if ($hidden->hiddenHere)
                                <form method="POST" action="{{ route('admin.categories.fields.destroy', [$category, $hidden->key]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-admin.button variant="secondary" size="sm" icon="eye">{{ __('admin.catalog.fields.unhide') }}</x-admin.button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-admin.form-section>
        @endif

        <x-slot:side>
            <x-admin.side-card :title="__('admin.catalog.fields.summary_card')" icon="adjustments" :link="[__('admin.catalog.fields.back_to_category'), route('admin.categories.edit', $category)]">
                <x-admin.meta-list>
                    <x-admin.meta-item :label="__('admin.catalog.categories.own_fields')">{{ number_format($view->ownCount()) }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.catalog.categories.inherited_fields')">{{ number_format($view->inheritedCount()) }}</x-admin.meta-item>
                    <x-admin.meta-item :label="__('admin.catalog.fields.hidden_count')">{{ number_format(count($view->hidden)) }}</x-admin.meta-item>
                </x-admin.meta-list>
            </x-admin.side-card>

            <x-admin.side-card :title="__('admin.catalog.fields.how_title')" icon="info">
                <ul class="grid list-disc gap-2 ps-5 text-sm text-ink-700">
                    <li>{{ __('admin.catalog.fields.how_inherit') }}</li>
                    <li>{{ __('admin.catalog.fields.how_override') }}</li>
                    <li>{{ __('admin.catalog.fields.how_hide') }}</li>
                    <li>{{ __('admin.catalog.fields.how_filterable') }}</li>
                </ul>
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection
