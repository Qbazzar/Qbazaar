<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Companies;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string for `GET /api/v1/companies`.
 *
 * @queryParam q string Optional search on the business or account name (≤100 chars).
 * @queryParam page int Default 1.
 */
class ListCompaniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function term(): ?string
    {
        $term = $this->validated('q');

        return is_string($term) ? trim($term) : null;
    }
}
