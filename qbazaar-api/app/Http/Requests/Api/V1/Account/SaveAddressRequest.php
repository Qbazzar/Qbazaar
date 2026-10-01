<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Rules\NoMarkup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Saved address payload: every required field on POST, any subset on PATCH.
 *
 * @bodyParam label string Optional name such as "Home". Example: Home
 * @bodyParam full_name string Recipient name. Example: Ahmed Al-Ali
 * @bodyParam phone string Recipient phone in E.164. Example: +97455123456
 * @bodyParam street string Street. Example: Al Sadd Street
 * @bodyParam house_number string Building or house number. Example: 12
 * @bodyParam supplement string Floor, flat or landmark. Example: Flat 4
 * @bodyParam city string City. Example: Doha
 * @bodyParam postal_code string Optional postal code. Example: 00000
 * @bodyParam location_id string Optional zone from /locations/qatar. Example: 01J0000000000000000000000
 * @bodyParam is_default boolean Make this the default address. Example: true
 */
class SaveAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:50', new NoMarkup],
            'full_name' => [$presence, 'string', 'min:2', 'max:80', new NoMarkup],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'street' => [$presence, 'string', 'max:120', new NoMarkup],
            'house_number' => [$presence, 'string', 'max:20', new NoMarkup],
            'supplement' => ['sometimes', 'nullable', 'string', 'max:120', new NoMarkup],
            'city' => [$presence, 'string', 'max:80', new NoMarkup],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20', 'alpha_dash'],
            'location_id' => ['sometimes', 'nullable', 'string', 'exists:locations,id'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function addressAttributes(): array
    {
        return collect($this->validated())->except('is_default')->all();
    }

    public function wantsDefault(): bool
    {
        return $this->boolean('is_default');
    }
}
