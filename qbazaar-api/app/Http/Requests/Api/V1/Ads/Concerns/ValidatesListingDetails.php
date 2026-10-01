<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads\Concerns;

use App\Enums\AdShipping;
use App\Enums\AdType;
use App\Rules\NoMarkup;
use Illuminate\Validation\Rule;

/**
 * Field rules shared by create and update; limits come from config('qbazaar.ads').
 */
trait ValidatesListingDetails
{
    /**
     * @return list<mixed>
     */
    protected function titleRules(): array
    {
        return [
            'string',
            'min:' . (int) config('qbazaar.ads.title_min_length'),
            'max:' . (int) config('qbazaar.ads.title_max_length'),
            new NoMarkup,
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function descriptionRules(): array
    {
        return [
            'string',
            'min:' . (int) config('qbazaar.ads.description_min_length'),
            'max:' . (int) config('qbazaar.ads.description_max_length'),
            new NoMarkup,
        ];
    }

    /**
     * @return list<string>
     */
    protected function priceRules(): array
    {
        return ['numeric', 'min:0', 'max:' . (int) config('qbazaar.ads.price_max')];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function listingDetailsRules(): array
    {
        return [
            'ad_type' => ['sometimes', Rule::enum(AdType::class)],
            'shipping' => ['sometimes', Rule::enum(AdShipping::class)],
            'postal_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:' . (int) config('qbazaar.ads.postal_code_max_length'),
                'regex:/^[0-9A-Za-z -]+$/',
            ],
            'street' => [
                'sometimes',
                'nullable',
                'string',
                'max:' . (int) config('qbazaar.ads.street_max_length'),
                new NoMarkup,
            ],
            'show_full_address' => ['sometimes', 'boolean'],
        ];
    }
}
