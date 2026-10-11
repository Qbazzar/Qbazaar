<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Search\Concerns;

use App\Enums\AccountType;
use App\Enums\AdShipping;
use App\Enums\AdType;
use App\Enums\Condition;
use App\Enums\PriceType;
use App\Rules\CatalogCustomFilters;
use Illuminate\Validation\Rule;

/**
 * The catalog filters `GET /search` and `GET /ads` have in common, so both
 * endpoints validate and mean the same thing.
 */
trait ValidatesCatalogFilters
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function catalogFilterRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'condition' => ['nullable', Rule::enum(Condition::class)],
            'price_type' => ['nullable', Rule::enum(PriceType::class)],
            'ad_type' => ['nullable', Rule::enum(AdType::class)],
            'shipping' => ['nullable', Rule::enum(AdShipping::class)],
            'seller_type' => ['nullable', Rule::enum(AccountType::class)],
            'buy_now' => ['nullable', 'boolean'],
            'custom_fields' => ['nullable', 'array', new CatalogCustomFilters],
        ];
    }

    /**
     * Query strings carry `buy_now=true`, which the boolean rule does not accept.
     */
    protected function normaliseBuyNow(): void
    {
        $buyNow = $this->query('buy_now');

        if (is_string($buyNow) && in_array(strtolower($buyNow), ['true', 'false'], true)) {
            $this->merge(['buy_now' => strtolower($buyNow) === 'true']);
        }
    }
}
