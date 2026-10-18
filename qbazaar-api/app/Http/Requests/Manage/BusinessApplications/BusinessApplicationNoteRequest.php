<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\BusinessApplications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The reason for a rejection, or what more staff need from the applicant.
 * The applicant reads it, so it is required and markup is stripped.
 */
class BusinessApplicationNoteRequest extends FormRequest
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
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function note(): string
    {
        return trim(strip_tags((string) $this->validated('note')));
    }
}
