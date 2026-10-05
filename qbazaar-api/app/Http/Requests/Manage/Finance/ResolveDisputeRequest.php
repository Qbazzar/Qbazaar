<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Finance;

use App\Enums\DisputeResolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveDisputeRequest extends FormRequest
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
            'resolution' => ['required', Rule::enum(DisputeResolution::class)],
            'note' => ['required', 'string', 'min:5', 'max:' . (int) config('qbazaar.orders.dispute_reason_max_length')],
        ];
    }

    public function resolution(): DisputeResolution
    {
        return DisputeResolution::from((string) $this->validated('resolution'));
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }
}
