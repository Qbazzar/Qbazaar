<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Search;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for `PATCH /api/v1/account/saved-searches/{id}` — partial update,
 * mainly to switch alerts on or off.
 *
 * @bodyParam alerts_enabled boolean
 * @bodyParam name string 1..60 chars.
 */
class PatchSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alerts_enabled' => ['required_without:name', 'boolean'],
            'name' => ['required_without:alerts_enabled', 'string', 'min:1', 'max:60'],
        ];
    }

    /**
     * @return array{name?: string, alerts_enabled?: bool}
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('name')) {
            $changes['name'] = (string) $this->validated('name');
        }

        if ($this->has('alerts_enabled')) {
            $changes['alerts_enabled'] = $this->boolean('alerts_enabled');
        }

        return $changes;
    }
}
