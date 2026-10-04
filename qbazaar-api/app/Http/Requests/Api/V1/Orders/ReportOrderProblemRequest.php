<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Orders;

use App\Rules\NoMarkup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam reason string What went wrong, for the admin who rules on it. Example: The phone does not turn on.
 */
class ReportOrderProblemRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:10', 'max:' . (int) config('qbazaar.orders.dispute_reason_max_length'), new NoMarkup],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
