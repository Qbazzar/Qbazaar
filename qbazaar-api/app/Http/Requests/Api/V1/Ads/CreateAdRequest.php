<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use App\Enums\Condition;
use App\Enums\PriceType;
use App\Models\Category;
use App\Services\Ads\CustomFieldsValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Body for `POST /api/v1/ads` — create a draft ad.
 *
 *  - `price` is force-nulled when `price_type` is `free` or `contact` so the
 *    storage layer never sees an inconsistent combination. Validators downstream
 *    can rely on the invariant.
 *  - `custom_fields` are stored as an arbitrary JSON object — category-specific
 *    schema validation lands in a follow-up (Sprint 5 nice-to-have).
 *
 * @bodyParam category_id string required ULID of the target category.
 * @bodyParam location_id string required ULID of the city / district.
 * @bodyParam title string required Listing title (5..120 chars).
 * @bodyParam description string required Listing description (20..5000 chars).
 * @bodyParam price number nullable Numeric price. Forced null when price_type is free|contact.
 * @bodyParam price_type string required One of `fixed`, `negotiable`, `free`, `contact`.
 * @bodyParam condition string nullable One of `new`, `like_new`, `used`.
 * @bodyParam custom_fields object nullable Category-specific attributes.
 */
class CreateAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Force-null the price for price types that disallow one. Doing this in
     * `prepareForValidation` ensures both the validator and the persisted
     * row stay consistent — the controller doesn't need to repeat the rule.
     */
    protected function prepareForValidation(): void
    {
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
            'category_id' => ['required', 'ulid', 'exists:categories,id'],
            'location_id' => ['required', 'ulid', 'exists:locations,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'title' => ['required', 'string', 'min:5', 'max:120'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'price_type' => ['required', Rule::in([
                PriceType::FIXED->value,
                PriceType::NEGOTIABLE->value,
                PriceType::FREE->value,
                PriceType::CONTACT->value,
            ])],
            'condition' => ['nullable', Rule::in([
                Condition::NEW->value,
                Condition::LIKE_NEW->value,
                Condition::USED->value,
            ])],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    /**
     * After the base rules pass, run the category-specific custom_fields
     * schema. We hook into withValidator() so the error messages join the
     * standard ValidationException — surfacing under VALIDATION_FAILED with
     * `custom_fields.{key}` paths.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $categoryId = $this->input('category_id');
            if (! is_string($categoryId)) {
                return;
            }

            $category = Category::query()->find($categoryId);
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
}
