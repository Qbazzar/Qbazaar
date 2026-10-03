<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Rules\NoMarkup;
use App\Rules\ValidIban;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam holder_name string The name on the account. Example: Ahmed Al-Ali
 * @bodyParam iban string The IBAN; spaces are ignored. Example: QA58 DOHB 0000 1234 5678 90AB CDEF G
 * @bodyParam bank_name string Optional bank name. Example: Doha Bank
 * @bodyParam is_default boolean Make this the payout account. Example: true
 */
class StoreBankAccountRequest extends FormRequest
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
            'holder_name' => ['required', 'string', 'min:2', 'max:120', new NoMarkup],
            'iban' => ['required', 'string', 'max:42', new ValidIban],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:120', new NoMarkup],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function holderName(): string
    {
        return trim((string) $this->validated('holder_name'));
    }

    public function iban(): string
    {
        return (string) $this->validated('iban');
    }

    public function bankName(): ?string
    {
        $name = $this->validated('bank_name');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    public function wantsDefault(): bool
    {
        return $this->boolean('is_default');
    }
}
