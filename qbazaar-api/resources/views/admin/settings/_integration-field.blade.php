@php
    $name = 'integrations[' . $setting->value . ']';
    $label = __('admin.integrations.fields.' . $setting->value . '.label');
    $hint = __('admin.integrations.fields.' . $setting->value . '.help') ?: null;
    $current = $field->secret ? null : $integrations->get($setting);
    $masked = $field->secret ? $integrations->masked($setting) : null;
    $value = old('integrations.' . $setting->value, $current);
@endphp

@if ($field->kind === \App\Enums\IntegrationFieldKind::TOGGLE)
    <x-admin.checkbox :name="$name" :label="$label" uncheckedValue="0" :checked="(string) old('integrations.' . $setting->value, $integrations->enabled($setting) ? '1' : '0') === '1'" class="self-end" />
@elseif ($field->kind === \App\Enums\IntegrationFieldKind::SELECT)
    <x-admin.select :name="$name" :label="$label" :hint="$hint">
        @foreach ($field->options as $option)
            <option value="{{ $option }}" @selected($value === $option || ($value === null && $loop->first))>{{ __('admin.integrations.options.' . $setting->value . '.' . $option) }}</option>
        @endforeach
    </x-admin.select>
@elseif ($field->kind === \App\Enums\IntegrationFieldKind::TEXTAREA)
    <div class="lg:col-span-2 2xl:col-span-3">
        {{-- A secret textarea never carries the stored value. --}}
        <x-admin.textarea :name="$name" :label="$label" :hint="$hint" :rows="6" :value="$field->secret ? null : $value" :placeholder="$masked" autocomplete="off" spellcheck="false" dir="ltr" />
        @include('admin.settings._integration-clear', ['masked' => $masked])
    </div>
@else
    <div>
        <x-admin.input
            :name="$name"
            :type="$field->secret ? 'password' : ($field->kind === \App\Enums\IntegrationFieldKind::EMAIL ? 'email' : ($field->kind === \App\Enums\IntegrationFieldKind::URL ? 'url' : ($field->kind === \App\Enums\IntegrationFieldKind::PHONE ? 'tel' : 'text')))"
            :label="$label"
            :hint="$hint"
            :value="$field->secret ? null : $value"
            :placeholder="$masked"
            :autocomplete="$field->secret ? 'new-password' : 'off'"
            :dir="str_ends_with($setting->value, '_ar') ? 'rtl' : 'ltr'"
        />
        @include('admin.settings._integration-clear', ['masked' => $masked])
    </div>
@endif
