<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage;

use App\Enums\IntegrationGroup;
use App\Enums\IntegrationSetting;
use App\Enums\PlatformSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (PlatformSetting::cases() as $setting) {
            $rules[$setting->value] = $setting->definition()->rules();
        }

        return [...$rules, ...$this->integrationRules()];
    }

    /**
     * Groups the actor may edit; input for any other group is never validated or saved.
     *
     * @return list<IntegrationGroup>
     */
    public function editableGroups(): array
    {
        return IntegrationGroup::visibleTo($this->user());
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function integrationRules(): array
    {
        $rules = ['integrations_clear' => ['sometimes', 'array'], 'integrations_clear.*' => ['boolean']];

        foreach ($this->editableGroups() as $group) {
            foreach (IntegrationSetting::forGroup($group) as $setting) {
                $rules["integrations.{$setting->value}"] = $setting->definition()->rules();
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (PlatformSetting::cases() as $setting) {
            $attributes[$setting->value] = (string) __("admin.settings.fields.{$setting->value}.label");
        }

        foreach (IntegrationSetting::cases() as $setting) {
            $attributes["integrations.{$setting->value}"] = (string) __("admin.integrations.fields.{$setting->value}.label");
        }

        return $attributes;
    }
}
