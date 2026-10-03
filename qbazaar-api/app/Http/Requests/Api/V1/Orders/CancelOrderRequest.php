<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Rules\NoMarkup;
use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:' . (int) config('qbazaar.orders.cancellation_reason_max_length'), new NoMarkup],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }
}
