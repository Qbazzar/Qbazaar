<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use App\Enums\Condition;
use App\Enums\PriceType;
use App\Models\Ad;
use App\Models\Category;
use App\Services\Ads\CustomFieldsValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
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

    /**
     * Run category-specific custom_fields validation. The target category is
     * either the new `category_id` (when changing categories on update) or
     * the ad's existing category — we resolve it lazily so partial updates
     * that only touch `custom_fields` still validate against the right schema.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->has('custom_fields')) {
                return;
            }

            $category = $this->resolveCategory();
            if ($category === null) {
                return;
            }

            $submitted = $this->input('custom_fields');
            /** @var array<string, mixed>|null $submitted */
            $submitted = is_array($submitted) ? $submitted : null;

            try {
                app(CustomFieldsValidator::class)->validate($category, $submitted);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $path => $errors) {
                    foreach ($errors as $msg) {
                        $v->errors()->add($path, $msg);
                    }
                }
            }
        });
    }

    /**
     * Resolve the target Category for custom_fields validation — explicit
     * `category_id` on the update wins; otherwise fall back to the ad's
     * existing category via the route binding (id segment).
     */
    private function resolveCategory(): ?Category
    {
        $explicit = $this->input('category_id');
        if (is_string($explicit) && $explicit !== '') {
            return Category::query()->find($explicit);
        }

        $adId = $this->route('id');
        if (! is_string($adId) || $adId === '') {
            return null;
        }

        $ad = Ad::query()->find($adId);

        return $ad?->category()->first();
    }
}
