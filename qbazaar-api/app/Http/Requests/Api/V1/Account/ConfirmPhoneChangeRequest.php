<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam code string required The OTP texted to the new number. Example: 123456
 */
class ConfirmPhoneChangeRequest extends FormRequest
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
        $length = (int) config('qbazaar.otp.length');

        return [
            'code' => ['required', 'string', 'regex:/^[0-9]{' . $length . '}$/'],
        ];
    }
}
