<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage;

use Illuminate\Foundation\Http\FormRequest;

class SendIntegrationTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('integrations.manage') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'test_email' => ['sometimes', 'required', 'email:rfc', 'max:190'],
            'test_phone' => ['sometimes', 'required', 'regex:/^\+[1-9]\d{6,14}$/'],
        ];
    }
}
