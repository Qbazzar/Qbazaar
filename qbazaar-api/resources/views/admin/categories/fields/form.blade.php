@extends('admin.layout')

@section('title', __('admin.catalog.fields.form_title.' . $form->mode))
@section('heading', __('admin.catalog.categories.title'))

@section('content')
    <x-admin.page
        :title="__('admin.catalog.fields.form_title.' . $form->mode)"
        :subtitle="$category->getLocalizedName(app()->getLocale())"
        :crumbs="[
            [__('admin.catalog.categories.title'), route('admin.categories.index')],
            [$category->getLocalizedName(app()->getLocale()), route('admin.categories.edit', $category)],
            [__('admin.catalog.fields.crumb'), route('admin.categories.fields.index', $category)],
            __('admin.catalog.fields.form_title.' . $form->mode),
        ]"
        :form="$form->isEdit() ? [route('admin.categories.fields.update', [$category, $form->key]), 'PUT'] : [route('admin.categories.fields.store', $category)]"
        :cancel="route('admin.categories.fields.index', $category)"
        :submit="__('admin.catalog.fields.save')"
        dirty-guard
    >
        @if ($form->inheritedFrom)
            <x-slot:lead>
                <x-admin.alert tone="info">{{ __('admin.catalog.fields.override_notice', ['name' => $form->inheritedFrom]) }}</x-admin.alert>
            </x-slot:lead>
        @endif

        <x-admin.form-section :title="__('admin.catalog.fields.sections.definition')" :description="__('admin.catalog.fields.sections.definition_hint')" cols="2">
            @if ($form->mode === 'create')
                <x-admin.input name="key" :label="__('admin.catalog.fields.attributes.key')" :value="old('key', $form->key)" dir="ltr" required
                    maxlength="40" pattern="[a-z][a-z0-9_]{0,39}" :hint="__('admin.catalog.fields.key_hint')" />
            @else
                @if ($form->mode === 'override')
                    <input type="hidden" name="key" value="{{ $form->key }}">
                @endif
                <x-admin.field for="field-key" :label="__('admin.catalog.fields.attributes.key')" :hint="__('admin.catalog.fields.key_locked')">
                    <p id="field-key" class="rounded-xl border border-ink-200 bg-cream-50 px-3 py-2.5 font-mono text-sm text-ink-700" dir="ltr">{{ $form->key }}</p>
                </x-admin.field>
            @endif
            <x-admin.select name="type" :label="__('admin.catalog.fields.attributes.type')" required data-qb-field-type :hint="__('admin.catalog.fields.type_hint')">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $form->type) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-admin.select>
            <x-admin.input name="label[ar]" :label="__('admin.catalog.fields.attributes.label_ar')" :value="old('label.ar', $form->labelAr)" maxlength="80" required />
            <x-admin.input name="label[en]" :label="__('admin.catalog.fields.attributes.label_en')" :value="old('label.en', $form->labelEn)" maxlength="80" dir="ltr" required />
        </x-admin.form-section>

        <x-admin.form-section :title="__('admin.catalog.fields.sections.options')" :description="__('admin.catalog.fields.sections.options_hint')" data-qb-for-types="select multiselect">
            <x-admin.textarea name="options" :label="__('admin.catalog.fields.attributes.options')" rows="10" dir="ltr" input-class="font-mono text-xs leading-relaxed"
                :hint="__('admin.catalog.fields.options_hint')" :value="old('options', $form->options)" />
        </x-admin.form-section>

        <x-admin.form-section :title="__('admin.catalog.fields.sections.bounds')" :description="__('admin.catalog.fields.sections.bounds_hint')" cols="2" data-qb-for-types="number range">
            <x-admin.input name="min" type="number" step="any" :label="__('admin.catalog.fields.attributes.min')" :value="old('min', $form->min)" dir="ltr" />
            <x-admin.input name="max" type="number" step="any" :label="__('admin.catalog.fields.attributes.max')" :value="old('max', $form->max)" dir="ltr" />
        </x-admin.form-section>

        <x-slot:side>
            <x-admin.side-card :title="__('admin.catalog.fields.sections.behaviour')">
                <div class="grid gap-4">
                    <x-admin.checkbox name="required" value="1" unchecked-value="0" :label="__('admin.catalog.fields.attributes.required')" :checked="(bool) old('required', $form->required)" />
                    <x-admin.checkbox name="filterable" value="1" unchecked-value="0" :label="__('admin.catalog.fields.attributes.filterable')" :checked="(bool) old('filterable', $form->filterable)" />
                    <x-admin.checkbox name="show_in_card" value="1" unchecked-value="0" :label="__('admin.catalog.fields.attributes.show_in_card')" :checked="(bool) old('show_in_card', $form->showInCard)" />
                    <x-admin.input name="sort_order" type="number" min="0" max="100000" :label="__('admin.catalog.fields.attributes.sort_order')" :value="old('sort_order', $form->sortOrder)" required
                        :hint="__('admin.catalog.fields.sort_hint')" />
                </div>
            </x-admin.side-card>
        </x-slot:side>
    </x-admin.page>
@endsection

@push('admin-scripts')
    <script>
        (function () {
            var type = document.querySelector('[data-qb-field-type]');
            if (!type) return;

            // Options only mean something for lists, bounds only for numbers.
            function sync() {
                document.querySelectorAll('[data-qb-for-types]').forEach(function (section) {
                    section.hidden = section.getAttribute('data-qb-for-types').split(' ').indexOf(type.value) === -1;
                });
            }
            type.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
