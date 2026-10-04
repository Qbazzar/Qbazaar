<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account\Concerns;

use App\Rules\NoMarkup;

/**
 * Delivery address fields, shared by the address book and the checkout's
 * inline address so both accept exactly the same shape.
 */
trait ValidatesAddressFields
{
    /**
     * @param string $presence rule applied to the required fields ('required', 'sometimes', ...)
     * @param string $prefix e.g. 'address.' when the fields are nested
     * @return array<string, list<mixed>>
     */
    protected function addressFieldRules(string $presence, string $prefix = ''): array
    {
        return [
            $prefix . 'full_name' => [$presence, 'string', 'min:2', 'max:80', new NoMarkup],
            $prefix . 'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            $prefix . 'street' => [$presence, 'string', 'max:120', new NoMarkup],
            $prefix . 'house_number' => [$presence, 'string', 'max:20', new NoMarkup],
            $prefix . 'supplement' => ['sometimes', 'nullable', 'string', 'max:120', new NoMarkup],
            $prefix . 'city' => [$presence, 'string', 'max:80', new NoMarkup],
            $prefix . 'postal_code' => ['sometimes', 'nullable', 'string', 'max:20', 'alpha_dash'],
            $prefix . 'location_id' => ['sometimes', 'nullable', 'string', 'exists:locations,id'],
        ];
    }
}
