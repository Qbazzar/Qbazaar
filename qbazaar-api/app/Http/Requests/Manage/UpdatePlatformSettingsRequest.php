<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage;

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

        return $attributes;
    }
}
