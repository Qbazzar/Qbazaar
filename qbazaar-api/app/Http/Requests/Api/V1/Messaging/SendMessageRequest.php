<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Messaging;

use App\Data\Messaging\ChatMessageDraft;
use App\Enums\MessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Body for `POST /api/v1/conversations/{id}/messages`.
 *
 *  - `type=text` (default): JSON or form body with a required `body`.
 *  - `type=image`: multipart with an `image` file and an optional `body`
 *    caption. The MIME allowlist is checked against the sniffed content,
 *    never the client file name.
 *
 * Offer and system bubbles have their own creation paths and are refused here.
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
        $maxLength = (int) config('qbazaar.messaging.max_message_length');

        return [
            'type' => ['sometimes', Rule::in([MessageType::TEXT->value, MessageType::IMAGE->value])],
            'body' => ['required_unless:type,' . MessageType::IMAGE->value, 'nullable', 'string', 'max:' . $maxLength],
            'image' => [
                'required_if:type,' . MessageType::IMAGE->value,
                'prohibited_unless:type,' . MessageType::IMAGE->value,
                'file',
                'mimetypes:' . implode(',', (array) config('qbazaar.uploads.allowed_mime_types')),
                'max:' . (int) config('qbazaar.uploads.max_image_size_kb'),
                Rule::dimensions()
                    ->maxWidth((int) config('qbazaar.messaging.image_max_side_px'))
                    ->maxHeight((int) config('qbazaar.messaging.image_max_side_px')),
            ],
        ];
    }

    public function draft(): ChatMessageDraft
    {
        $body = $this->validated('body');
        $image = $this->file('image');

        if ($image instanceof UploadedFile) {
            return ChatMessageDraft::image($image, is_string($body) ? $body : null);
        }

        return ChatMessageDraft::text((string) $body);
    }
}
