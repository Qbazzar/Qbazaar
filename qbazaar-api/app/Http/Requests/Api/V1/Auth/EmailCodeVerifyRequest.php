<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\Concerns\AcceptsRegistrationDetails;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam email string required Address the code was sent to. Example: ahmed@example.qa
 * @bodyParam code string required The 6-digit code. Example: 482915
 * @bodyParam full_name string Sign-up only. Example: Ahmed Al-Ali
 * @bodyParam phone string Sign-up only, +974XXXXXXXX. Example: +97455123456
 * @bodyParam account_type string Sign-up only, `private` or `business`. Example: private
 * @bodyParam language string Sign-up only, `ar` or `en`. Example: ar
 * @bodyParam accepted_terms boolean Sign-up only, must be `true`. Example: true
 */
class EmailCodeVerifyRequest extends FormRequest
{
    use AcceptsRegistrationDetails;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $length = (int) config('qbazaar.otp.length', 6);

        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'code' => ['required', 'string', 'regex:/^[0-9]{' . $length . '}$/'],
            ...$this->registrationRules(),
        ];
    }
}
