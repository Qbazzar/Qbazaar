<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Search;

use App\Enums\Condition;
use App\Enums\PriceType;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string for `GET /api/v1/search`.
 *
 * Slug filters are resolved to ULIDs in {@see prepareForValidation} so the
 * controller only deals with IDs. Slug rows that don't exist are NOT a
 * validation error — we treat them as "no rows match" upstream so a stale
 * link from an external page just returns an empty result set.
 *
 * @queryParam q string Optional keyword (≤200 chars).
 * @queryParam category_id string Optional ULID filter.
 * @queryParam category_slug string Optional slug — resolved to category_id.
 * @queryParam location_id string Optional ULID filter.
 * @queryParam location_slug string Optional slug — resolved to location_id.
 * @queryParam price_min number Optional minimum price.
 * @queryParam price_max number Optional maximum price.
 * @queryParam condition string Optional `new|like_new|used`.
 * @queryParam price_type string Optional `fixed|negotiable|free|contact`.
 * @queryParam sort string `latest|oldest|price_asc|price_desc`. Defaults to `latest`.
 * @queryParam page int Default 1.
 * @queryParam per_page int Default 20, max 50.
 */
class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Resolve slug filters to ULIDs BEFORE the validator runs, so the rules
     * downstream can rely on `category_id` / `location_id` alone. Missing
     * slugs do NOT fail validation — they fall through as nulls and the
     * search returns an empty page, which is the friendlier behaviour for
     * deep links from old emails / share URLs.
     */
    protected function prepareForValidation(): void
    {
        $resolved = [];

        if (($categorySlug = $this->query('category_slug')) !== null && is_string($categorySlug) && $categorySlug !== '') {
            /** @var Category|null $category */
            $category = Category::query()->where('slug', $categorySlug)->first();
            if ($category !== null) {
                $resolved['category_id'] = $category->id;
            }
        }

        if (($locationSlug = $this->query('location_slug')) !== null && is_string($locationSlug) && $locationSlug !== '') {
            /** @var Location|null $location */
            $location = Location::query()->where('slug', $locationSlug)->first();
            if ($location !== null) {
                $resolved['location_id'] = $location->id;
            }
        }

        if ($resolved !== []) {
            $this->merge($resolved);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'ulid'],
            'category_slug' => ['nullable', 'string', 'max:120'],
            'location_id' => ['nullable', 'ulid'],
            'location_slug' => ['nullable', 'string', 'max:120'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'gte:price_min'],
            'condition' => ['nullable', Rule::in([
                Condition::NEW->value,
                Condition::LIKE_NEW->value,
                Condition::USED->value,
            ])],
            'price_type' => ['nullable', Rule::in([
                PriceType::FIXED->value,
                PriceType::NEGOTIABLE->value,
                PriceType::FREE->value,
                PriceType::CONTACT->value,
            ])],
            'sort' => ['nullable', Rule::in(['latest', 'oldest', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
