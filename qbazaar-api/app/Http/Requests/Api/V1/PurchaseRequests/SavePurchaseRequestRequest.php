<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\PurchaseRequests;

use App\Rules\NoMarkup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for `POST /ads/{id}/purchase-requests` and `PUT /purchase-requests/{id}`.
 * The price is never sent: it is the ad's price, read on the server. How
 * many units the ad offers is checked against the ad under its lock.
 *
 * @bodyParam quantity integer Units to buy; defaults to 1. Example: 1
 * @bodyParam note string Optional note to the seller. Example: Can I pick it up today?
 */
class SavePurchaseRequestRequest extends FormRequest
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
        return [
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:' . (int) config('qbazaar.ads.quantity_max')],
            'note' => ['sometimes', 'nullable', 'string', 'max:' . (int) config('qbazaar.purchase_requests.note_max_length'), new NoMarkup],
        ];
    }

    public function quantity(): int
    {
        return (int) ($this->validated('quantity') ?? 1);
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
