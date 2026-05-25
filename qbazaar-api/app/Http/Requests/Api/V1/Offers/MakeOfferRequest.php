<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Offers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for `POST /api/v1/conversations/{id}/offers`.
 *
 *  - `amount` shares its upper bound with the ads `price_max` setting so
 *    a buyer can offer up to (but not beyond) what the seller could
 *    have listed the ad for. Lower bound is `1` — a zero or negative
 *    offer is non-sensical for QBazaar's marketplace model.
 *  - `note` is capped at 280 chars to keep the offer card compact;
 *    longer commentary belongs in regular chat.
 */
class MakeOfferRequest extends FormRequest
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
        $max = (int) config('qbazaar.ads.price_max', 99_999_999);

        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:' . $max],
            'note' => ['nullable', 'string', 'max:280'],
        ];
    }
}
