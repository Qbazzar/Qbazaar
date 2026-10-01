<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Ad;
use App\Models\SavedSearch;
use App\Services\Catalog\CategoryHierarchy;
use App\Services\Catalog\LocationHierarchy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides which saved searches a newly live ad satisfies, with the same
 * meaning `/search` gives each filter.
 *
 * {@see candidates()} narrows in SQL on the indexed criteria columns: a
 * parent category or location also matches ads in its descendants, and a
 * price range never matches an ad without a price. {@see matchesDetails()}
 * then checks the keyword and custom-field filters, which only exist in the
 * JSON, on the rows that are left.
 */
class SavedSearchMatcher
{
    public function __construct(
        private readonly CategoryHierarchy $categories,
        private readonly LocationHierarchy $locations,
    ) {}

    /**
     * @return Builder<SavedSearch>
     */
    public function candidates(Ad $ad): Builder
    {
        return SavedSearch::query()
            ->where('alerts_enabled', true)
            ->where('user_id', '!=', $ad->user_id)
            ->where(fn (Builder $q) => $q->whereNull('category_id')->orWhereIn('category_id', $this->categories->pathTo($ad->category_id)))
            ->where(fn (Builder $q) => $q->whereNull('location_id')->orWhereIn('location_id', $this->locations->pathTo($ad->location_id)))
            ->where(fn (Builder $q) => $q->whereNull('price_type')->orWhere('price_type', $ad->price_type->value))
            ->where(fn (Builder $q) => $q->whereNull('condition')->when(
                $ad->condition !== null,
                fn (Builder $q) => $q->orWhere('condition', $ad->condition?->value),
            ))
            ->when(
                $ad->price === null,
                fn (Builder $q) => $q->whereNull('price_min')->whereNull('price_max'),
                fn (Builder $q) => $q
                    ->where(fn (Builder $min) => $min->whereNull('price_min')->orWhere('price_min', '<=', $ad->price))
                    ->where(fn (Builder $max) => $max->whereNull('price_max')->orWhere('price_max', '>=', $ad->price)),
            );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function matchesDetails(Ad $ad, array $params): bool
    {
        return $this->matchesKeyword($ad, $params['q'] ?? null)
            && $this->matchesCustomFields($ad, $params['custom_fields'] ?? null);
    }

    /**
     * Every word of the keyword must appear in the title or description.
     */
    private function matchesKeyword(Ad $ad, mixed $keyword): bool
    {
        if (! is_string($keyword) || trim($keyword) === '') {
            return true;
        }

        $haystack = mb_strtolower($ad->title . ' ' . $ad->description);

        foreach (preg_split('/\s+/u', mb_strtolower(trim($keyword))) ?: [] as $word) {
            if (! str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Same shape as `/search`: `{make: "Toyota"}` for equality,
     * `{year: {min: 2015, max: 2020}}` for a range.
     */
    private function matchesCustomFields(Ad $ad, mixed $filters): bool
    {
        if (! is_array($filters)) {
            return true;
        }

        $fields = is_array($ad->custom_fields) ? $ad->custom_fields : [];

        foreach ($filters as $key => $expected) {
            if (! is_string($key) || preg_match('/^[a-z0-9_]+$/i', $key) !== 1) {
                continue;
            }

            if (! $this->matchesField($fields[$key] ?? null, $expected)) {
                return false;
            }
        }

        return true;
    }

    private function matchesField(mixed $actual, mixed $expected): bool
    {
        if (is_array($expected)) {
            $min = $expected['min'] ?? null;
            $max = $expected['max'] ?? null;

            if (! is_numeric($min) && ! is_numeric($max)) {
                return true;
            }

            return is_numeric($actual)
                && (! is_numeric($min) || (float) $actual >= (float) $min)
                && (! is_numeric($max) || (float) $actual <= (float) $max);
        }

        if (! is_string($expected) || $expected === '') {
            return true;
        }

        return is_scalar($actual) && mb_strtolower((string) $actual) === mb_strtolower($expected);
    }
}
