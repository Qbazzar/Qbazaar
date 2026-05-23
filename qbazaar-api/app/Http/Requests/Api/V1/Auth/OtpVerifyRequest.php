<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/v1/auth/verify-otp. Mirrors the OpenAPI
 * `OtpVerifyRequest` schema (6-digit numeric).
 *
 * @bodyParam phone string required Qatari phone in `+974XXXXXXXX` shape. Example: +97455123456
 * @bodyParam code string required 6-digit numeric OTP. Example: 482915
 */
class OtpVerifyRequest extends FormRequest
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
        $length = (int) config('qbazaar.otp.length', 6);

        return [
            'phone' => [
                'required',
                'string',
                'regex:' . config('qbazaar.phone_regex'),
            ],
            'code' => [
                'required',
                'string',
                'regex:/^[0-9]{' . $length . '}$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number must be a valid Qatari number (+974XXXXXXXX).',
            'code.regex' => 'The code must be a numeric value of the configured length.',
        ];
    }
}
