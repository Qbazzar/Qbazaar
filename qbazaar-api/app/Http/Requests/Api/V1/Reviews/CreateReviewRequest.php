<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Reviews;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for `POST /api/v1/ads/{ad}/reviews`. Eligibility (accepted offer, not
 * own ad, no duplicate) is enforced in the controller, not here.
 *
 * @bodyParam rating int required 1..5 stars.
 * @bodyParam comment string Optional free-text (≤1000 chars).
 */
class CreateReviewRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
