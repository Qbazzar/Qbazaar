<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Finance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The reason a settlement or withdrawal is rejected. The seller reads it.
 */
class RejectFinanceRequest extends FormRequest
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
        return trim((string) $this->validated('reason'));
    }
}
