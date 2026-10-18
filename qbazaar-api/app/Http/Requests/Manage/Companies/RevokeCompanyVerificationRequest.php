<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Companies;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Why staff remove a company's verified badge. The owner reads it in the
 * notification, so markup is stripped.
 */
class RevokeCompanyVerificationRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return trim(strip_tags((string) $this->validated('reason')));
    }
}
