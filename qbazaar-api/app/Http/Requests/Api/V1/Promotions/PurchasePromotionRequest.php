<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Promotions;

use App\Enums\PromotionPaymentMethod;
use App\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @bodyParam type string required One of highlight, push_up, gallery, premium. Example: premium
 * @bodyParam payment_method string required wallet or bank_transfer. Example: bank_transfer
 * @bodyParam transfer_reference string The reference of the bank transfer; required for bank_transfer. Example: TRX-204918
 */
class PurchasePromotionRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::enum(PromotionType::class)],
            'payment_method' => ['required', 'string', Rule::enum(PromotionPaymentMethod::class)],
            'transfer_reference' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('payment_method') === PromotionPaymentMethod::BANK_TRANSFER->value),
                'string',
                'max:' . (int) config('qbazaar.promotions.transfer_reference_max_length'),
                'regex:/^[A-Za-z0-9][A-Za-z0-9 \/._-]*$/',
            ],
        ];
    }

    public function type(): PromotionType
    {
        return PromotionType::from((string) $this->validated('type'));
    }

    public function paymentMethod(): PromotionPaymentMethod
    {
        return PromotionPaymentMethod::from((string) $this->validated('payment_method'));
    }

    public function transferReference(): ?string
    {
        $reference = $this->validated('transfer_reference');

        return is_string($reference) && trim($reference) !== '' ? trim($reference) : null;
    }
}
