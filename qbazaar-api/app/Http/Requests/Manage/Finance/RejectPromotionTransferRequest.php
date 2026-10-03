<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Finance;

use App\Rules\NoMarkup;
use Illuminate\Foundation\Http\FormRequest;

class RejectPromotionTransferRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:500', new NoMarkup],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }
}
