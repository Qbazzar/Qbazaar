<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Messaging;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for `POST /api/v1/conversations` — start (or resolve) a chat
 * thread about an ad.
 *
 * @bodyParam ad_id string required ULID of the target ad.
 */
class StartConversationRequest extends FormRequest
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
            'ad_id' => ['required', 'ulid', 'exists:ads,id'],
        ];
    }
}
