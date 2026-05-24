<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Messaging;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query params for `GET /api/v1/conversations/{id}/messages` —
 * cursor-paginated transcript.
 *
 *  - `before` is the ULID of a message; the response returns messages
 *    strictly older than it (newest-first). When omitted, returns the
 *    most recent page.
 *  - `limit` is capped at 100 so clients can't request unbounded
 *    payloads; 50 matches the default chat-screen page size.
 *
 * @queryParam before string nullable ULID of a message; results are strictly older than this.
 * @queryParam limit integer nullable Page size (1..100, default 50).
 */
class MessagesIndexRequest extends FormRequest
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
            'before' => ['sometimes', 'nullable', 'ulid'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
