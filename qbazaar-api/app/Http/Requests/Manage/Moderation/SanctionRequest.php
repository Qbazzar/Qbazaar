<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Moderation;

use App\Data\Moderation\SanctionReasonInput;
use App\Enums\SanctionReason;
use App\Rules\NoMarkup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A preset reason plus an internal note, required for every suspension,
 * removal, bulk rejection and cancelled offer. The note is mandatory only for
 * "other". Route permissions authorise; subclasses add their own fields.
 */
class SanctionRequest extends FormRequest
{
    public const int NOTE_MAX_LENGTH = 1000;

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
            'reason' => ['required', 'string', Rule::enum(SanctionReason::class)],
            'reason_note' => [
                'nullable',
                Rule::requiredIf(fn (): bool => SanctionReason::tryFrom((string) $this->input('reason'))?->requiresNote() === true),
                'string',
                'max:' . self::NOTE_MAX_LENGTH,
                new NoMarkup,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => (string) __('admin.moderation.fields.reason'),
            'reason_note' => (string) __('admin.moderation.fields.note'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => (string) __('admin.moderation.validation.reason_required'),
            'reason.enum' => (string) __('admin.moderation.validation.reason_invalid'),
            'reason_note.required' => (string) __('admin.moderation.validation.note_required'),
            'reason_note.max' => (string) __('admin.moderation.validation.note_too_long', ['max' => self::NOTE_MAX_LENGTH]),
        ];
    }

    public function sanction(): SanctionReasonInput
    {
        $note = trim((string) $this->validated('reason_note'));

        return new SanctionReasonInput(
            SanctionReason::from((string) $this->validated('reason')),
            $note === '' ? null : $note,
        );
    }
}
