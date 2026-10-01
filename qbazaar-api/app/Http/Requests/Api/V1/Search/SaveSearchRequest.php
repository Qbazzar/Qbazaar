<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Search;

use App\Enums\Condition;
use App\Enums\PriceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body for `POST /api/v1/account/saved-searches` and the full replace
 * `PUT /api/v1/account/saved-searches/{id}`.
 *
 * `query_params` stays an open envelope replayed against `/search`, but the
 * filters alerts rely on are checked with the same rules `/search` applies.
 *
 * @bodyParam name string required Display name (1..60 chars).
 * @bodyParam query_params object required The search inputs.
 * @bodyParam alerts_enabled boolean Push/bell alerts for new matching ads (default true).
 */
class SaveSearchRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:60'],
            'query_params' => ['required', 'array'],
            'query_params.q' => ['nullable', 'string', 'max:200'],
            'query_params.category_id' => ['nullable', 'ulid'],
            'query_params.category_slug' => ['nullable', 'string', 'max:120'],
            'query_params.location_id' => ['nullable', 'ulid'],
            'query_params.location_slug' => ['nullable', 'string', 'max:120'],
            'query_params.price_min' => ['nullable', 'numeric', 'min:0'],
            'query_params.price_max' => ['nullable', 'numeric', 'min:0'],
            'query_params.condition' => ['nullable', Rule::enum(Condition::class)],
            'query_params.price_type' => ['nullable', Rule::enum(PriceType::class)],
            'query_params.custom_fields' => ['nullable', 'array'],
            'alerts_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, query_params: array<string, mixed>, alerts_enabled?: bool}
     */
    public function savedSearch(): array
    {
        $attributes = [
            'name' => (string) $this->validated('name'),
            'query_params' => (array) $this->validated('query_params'),
        ];

        if ($this->has('alerts_enabled')) {
            $attributes['alerts_enabled'] = $this->boolean('alerts_enabled');
        }

        return $attributes;
    }
}
