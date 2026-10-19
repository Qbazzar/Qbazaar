<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Conversations;

use App\Rules\NoMarkup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The reason a staff member gives before reading a private conversation.
 * It is shown to colleagues on the transcript and kept in the audit log.
 */
class OpenConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('conversations.view') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:' . (int) config('qbazaar.admin.conversation_access.reason_min_length'),
                'max:' . (int) config('qbazaar.admin.conversation_access.reason_max_length'),
                new NoMarkup,
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => (string) __('admin.conversation_access.reason')];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }

    public function page(): ?int
    {
        $page = $this->validated('page');

        return $page === null ? null : (int) $page;
    }
}
