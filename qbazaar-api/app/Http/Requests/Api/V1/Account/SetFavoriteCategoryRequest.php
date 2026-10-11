<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT|DELETE /account/favorite-categories/{category}`: the path carries the
 * category slug, validated before it reaches a query.
 */
class SetFavoriteCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['category' => $this->route('category')]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/i'],
        ];
    }

    public function slug(): string
    {
        return (string) $this->validated('category');
    }
}
