<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Test sends take no recipient input: they always go to the signed-in admin,
 * so the action cannot be used to message third parties.
 */
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
        return [];
    }
}
