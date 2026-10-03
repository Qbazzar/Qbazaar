<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Enums\SettlementMethod;
use App\Http\Requests\Api\V1\Wallet\Concerns\ValidatesMoneyAmount;
use App\Rules\NoMarkup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * @bodyParam method string `bank_transfer` (reviewed by finance) or `wallet` (netted at once). Example: bank_transfer
 * @bodyParam amount string The amount paid, at most the commission owed. Example: 150.00
 * @bodyParam bank_reference string The transfer's reference number (bank transfer only). Example: TRX-2026-0042
 * @bodyParam proof file A photo or screenshot of the transfer receipt (bank transfer only).
 */
class StoreSettlementRequest extends FormRequest
{
    use ValidatesMoneyAmount;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bankTransfer = SettlementMethod::BANK_TRANSFER->value;

        return [
            'method' => ['required', Rule::enum(SettlementMethod::class)],
            'amount' => $this->amountRules(),
            'bank_reference' => ["required_if:method,{$bankTransfer}", "prohibited_unless:method,{$bankTransfer}", 'string', 'max:100', new NoMarkup],
            'proof' => [
                "required_if:method,{$bankTransfer}",
                "prohibited_unless:method,{$bankTransfer}",
                'file',
                'mimetypes:' . implode(',', (array) config('qbazaar.uploads.allowed_mime_types')),
                'max:' . (int) config('qbazaar.wallet.proof_max_size_kb'),
            ],
        ];
    }

    public function settlementMethod(): SettlementMethod
    {
        return SettlementMethod::from((string) $this->validated('method'));
    }

    public function bankReference(): string
    {
        return trim((string) $this->validated('bank_reference'));
    }

    public function proof(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('proof');
    }
}
