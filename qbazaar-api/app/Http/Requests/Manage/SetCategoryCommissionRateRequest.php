<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetCategoryCommissionRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'string', Rule::exists('categories', 'id')],
            'rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => (string) __('admin.commission_rates.category'),
            'rate' => (string) __('admin.commission_rates.rate'),
        ];
    }
}
