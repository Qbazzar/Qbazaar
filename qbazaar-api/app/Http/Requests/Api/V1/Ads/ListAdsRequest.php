<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use App\Data\Catalog\AdFeedFilters;
use App\Enums\AdSort;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of `GET /api/v1/ads`.
 *
 * `ids` accepts a comma-separated list (`?ids=a,b,c`) or the array form
 * (`?ids[]=a&ids[]=b`); duplicates are dropped and the first position wins.
 */
class ListAdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->query('ids');

        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        if (! is_array($ids)) {
            return;
        }

        $normalized = array_values(array_unique(array_filter(
            // Stored ULIDs are lower-case; normalising keeps the order lookup exact for upper-case input.
            array_map(static fn (mixed $id): mixed => is_string($id) ? strtolower(trim($id)) : $id, $ids),
            static fn (mixed $id): bool => $id !== '' && $id !== null,
        ), SORT_REGULAR));

        $this->merge(['ids' => $normalized === [] ? null : $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['nullable', 'array', 'max:' . (int) config('qbazaar.search.ids_lookup_max')],
            'ids.*' => ['string', 'ulid'],
            'category_id' => ['nullable', 'ulid'],
            'location_id' => ['nullable', 'ulid'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', Rule::in(AdSort::feedValues())],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $min = $this->input('price_min');
            $max = $this->input('price_max');

            if (is_numeric($min) && is_numeric($max) && (float) $max < (float) $min) {
                $v->errors()->add('price_max', (string) __('validation.gte.numeric', [
                    'attribute' => 'price_max',
                    'value' => 'price_min',
                ]));
            }
        });
    }

    /**
     * @return list<string>|null
     */
    public function ids(): ?array
    {
        /** @var list<string>|null $ids */
        $ids = $this->validated('ids');

        return $ids;
    }

    public function filters(): AdFeedFilters
    {
        $sort = $this->validated('sort');

        return new AdFeedFilters(
            categoryId: $this->stringOrNull('category_id'),
            locationId: $this->stringOrNull('location_id'),
            priceMin: is_numeric($min = $this->validated('price_min')) ? (float) $min : null,
            priceMax: is_numeric($max = $this->validated('price_max')) ? (float) $max : null,
            sort: is_string($sort) ? AdSort::from($sort) : AdSort::LATEST,
        );
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
