<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Messaging;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for `DELETE /api/v1/conversations` — hide conversations in bulk.
 *
 * @bodyParam ids string[] required Conversation ULIDs (at most `qbazaar.messaging.bulk_hide_max`).
 */
class HideConversationsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:' . (int) config('qbazaar.messaging.bulk_hide_max')],
            'ids.*' => ['required', 'string', 'ulid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function conversationIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated('ids');

        return $ids;
    }
}
