<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Finance;

use Illuminate\Foundation\Http\FormRequest;

class MarkWithdrawalPaidRequest extends FormRequest
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
            'transfer_reference' => ['required', 'string', 'max:100'],
        ];
    }

    public function transferReference(): string
    {
        return trim((string) $this->validated('transfer_reference'));
    }
}
