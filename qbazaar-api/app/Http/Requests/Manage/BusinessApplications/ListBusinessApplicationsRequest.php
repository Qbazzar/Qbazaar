<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\BusinessApplications;

use App\Enums\BusinessApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of the business applications queue. Without a status the
 * queue shows what waits for staff.
 */
class ListBusinessApplicationsRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(BusinessApplicationStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function status(): BusinessApplicationStatus
    {
        return BusinessApplicationStatus::tryFrom((string) $this->validated('status')) ?? BusinessApplicationStatus::PENDING;
    }

    public function search(): ?string
    {
        $term = $this->validated('q');

        return is_string($term) && trim($term) !== '' ? trim($term) : null;
    }
}
