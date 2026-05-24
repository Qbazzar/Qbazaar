<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Messaging;

use App\Enums\MessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body for `POST /api/v1/conversations/{id}/messages` — append a chat
 * message to a conversation.
 *
 *  - `body` is bounded by `config('qbazaar.messaging.max_message_length')`
 *    so the limit stays consistent between the validator, the resource,
 *    and any future client-side mirror.
 *  - `type` only accepts `text` in Wave A. `offer` / `system` ship in
 *    Sprint 9 alongside their own dedicated creation paths; rejecting them
 *    here keeps the contract honest.
 */
class SendMessageRequest extends FormRequest
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
        $max = (int) config('qbazaar.messaging.max_message_length', 4000);

        return [
            'body' => ['required', 'string', 'min:1', 'max:' . $max],
            'type' => ['sometimes', Rule::in([MessageType::TEXT->value])],
        ];
    }
}
