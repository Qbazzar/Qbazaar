<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads\Concerns;

use App\Enums\AdShipping;
use App\Enums\AdType;
use App\Rules\NoMarkup;
use Illuminate\Validation\Rule;

/**
 * Rules for the ad type, shipping and address fields, shared by create and update.
 */
trait ValidatesListingDetails
{
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
