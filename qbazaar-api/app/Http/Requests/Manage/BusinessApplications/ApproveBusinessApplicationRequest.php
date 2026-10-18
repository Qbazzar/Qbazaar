<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\BusinessApplications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An optional note to the applicant when staff approve their application.
 */
class ApproveBusinessApplicationRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function note(): ?string
    {
        $note = trim(strip_tags((string) $this->validated('note')));

        return $note === '' ? null : $note;
    }
}
