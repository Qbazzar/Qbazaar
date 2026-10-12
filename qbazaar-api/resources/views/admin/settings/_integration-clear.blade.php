@if ($masked !== null)
    <x-admin.checkbox :name="'integrations_clear[' . $setting->value . ']'" :label="__('admin.integrations.clear')" class="mt-2" />
@endif
