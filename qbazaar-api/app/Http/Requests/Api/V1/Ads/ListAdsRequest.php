<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use App\Data\Catalog\AdFeedFilters;
use App\Enums\AdSort;
use App\Http\Requests\Api\V1\Search\Concerns\ValidatesCatalogFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Query string of `GET /api/v1/ads`.
 *
 * `ids` accepts a comma-separated list (`?ids=a,b,c`) or the array form
 * (`?ids[]=a&ids[]=b`); duplicates are dropped and the first position wins.
 */
class ListAdsRequest extends FormRequest
{
    use ValidatesCatalogFilters;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseBuyNow();

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
            ...$this->catalogFilterRules(),
            'ids' => ['nullable', 'array', 'max:' . (int) config('qbazaar.search.ids_lookup_max')],
            'ids.*' => ['string', 'ulid'],
            'category_id' => ['nullable', 'ulid'],
            'location_id' => ['nullable', 'ulid'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', Rule::in(AdSort::feedValues())],
            'page' => ['nullable', 'integer', 'min:1', 'max:' . (int) config('qbazaar.ads.feed_max_page')],
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

    /**
     * The validated filters, sort and page, for the shared search service.
     *
     * @return array<string, mixed>
     */
    public function searchParams(): array
    {
        return Arr::except($this->validated(), ['ids']);
    }

    public function filters(): AdFeedFilters
    {
        return AdFeedFilters::fromParams($this->searchParams());
    }
}
