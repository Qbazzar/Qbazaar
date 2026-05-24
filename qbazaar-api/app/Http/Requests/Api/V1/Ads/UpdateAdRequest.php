<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use App\Enums\Condition;
use App\Enums\PriceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body for `PUT /api/v1/ads/{id}` — partial update.
 *
 * Every field is `sometimes` — callers only need to send the keys they're
 * changing. The same price-type / price coercion as CreateAdRequest applies
 * when both fields are present.
 */
class UpdateAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('price_type')) {
            return;
        }

        $priceType = $this->input('price_type');

        if (in_array($priceType, [PriceType::FREE->value, PriceType::CONTACT->value], true)) {
            $this->merge(['price' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'ulid', 'exists:categories,id'],
            'location_id' => ['sometimes', 'ulid', 'exists:locations,id'],
            'title' => ['sometimes', 'string', 'min:5', 'max:120'],
            'description' => ['sometimes', 'string', 'min:20', 'max:5000'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999'],
            'price_type' => ['sometimes', Rule::in([
                PriceType::FIXED->value,
                PriceType::NEGOTIABLE->value,
                PriceType::FREE->value,
                PriceType::CONTACT->value,
            ])],
            'condition' => ['sometimes', 'nullable', Rule::in([
                Condition::NEW->value,
                Condition::LIKE_NEW->value,
                Condition::USED->value,
            ])],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
