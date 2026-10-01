<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `POST /api/v1/ads/{id}/publish`. Ownership and status rules live in
 * AdPolicy::publish() and PublishAdAction.
 */
class PublishAdRequest extends FormRequest
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
            'accepted_terms' => ['required', 'accepted'],
        ];
    }
}
