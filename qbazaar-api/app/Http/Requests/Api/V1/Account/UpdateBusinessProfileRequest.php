<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Rules\NoMarkup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body for `PUT /api/v1/account/business-profile`. Only the fields sent are
 * changed; null clears a field.
 *
 * `opening_hours` is a list of at most one entry per weekday:
 * `{ "day": "sat", "closed": false, "open": "09:00", "close": "21:00" }`.
 * Times may cross midnight, so close is not required to be after open.
 */
class UpdateBusinessProfileRequest extends FormRequest
{
    public const WEEKDAYS = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['sometimes', 'nullable', 'string', 'max:120', new NoMarkup],
            'about' => ['sometimes', 'nullable', 'string', 'max:' . (int) config('qbazaar.social.business_about_max_length'), new NoMarkup],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:160', new NoMarkup],
            'commercial_registration_number' => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[0-9A-Za-z\/-]+$/'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'regex:' . (string) config('qbazaar.phone_regex')],
            'contact_email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255', new NoMarkup],
            'opening_hours' => ['sometimes', 'nullable', 'array', 'max:' . count(self::WEEKDAYS)],
            'opening_hours.*' => ['array:day,closed,open,close'],
            'opening_hours.*.day' => ['required', 'string', 'distinct', Rule::in(self::WEEKDAYS)],
            'opening_hours.*.closed' => ['sometimes', 'boolean'],
            'opening_hours.*.open' => ['required_unless:opening_hours.*.closed,true', 'nullable', 'date_format:H:i'],
            'opening_hours.*.close' => ['required_unless:opening_hours.*.closed,true', 'nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Validated fields with every opening-hours entry in one shape.
     *
     * @return array<string, mixed>
     */
    public function profileAttributes(): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $this->validated();

        if (is_array($attributes['opening_hours'] ?? null)) {
            $attributes['opening_hours'] = array_map(static function (array $entry): array {
                $closed = (bool) ($entry['closed'] ?? false);

                return [
                    'day' => $entry['day'],
                    'closed' => $closed,
                    'open' => $closed ? null : $entry['open'],
                    'close' => $closed ? null : $entry['close'],
                ];
            }, array_values($attributes['opening_hours']));
        }

        return $attributes;
    }
}
