<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\Companies;

use App\Enums\CompanyVerificationFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of the admin companies list. An unknown filter value is a
 * validation error rather than a silently ignored one.
 */
class ListCompaniesRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'verification' => ['nullable', Rule::enum(CompanyVerificationFilter::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function search(): ?string
    {
        $term = $this->validated('q');

        return is_string($term) && trim($term) !== '' ? trim($term) : null;
    }

    public function verification(): ?CompanyVerificationFilter
    {
        return CompanyVerificationFilter::tryFrom((string) $this->validated('verification'));
    }
}
