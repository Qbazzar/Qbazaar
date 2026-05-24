<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Search;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for `POST /api/v1/account/saved-searches`.
 *
 * The `query_params` payload is treated as an opaque envelope — we don't
 * re-validate each filter here so the saved-search schema doesn't need to
 * track every Sprint's worth of search params. The downside (a stale saved
 * search referencing a deleted slug) is acceptable: the client just sees
 * an empty result page and can update the saved query.
 *
 * @bodyParam name string required Display name (1..60 chars).
 * @bodyParam query_params object required Arbitrary JSON object capturing the search inputs.
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
        ];
    }
}
