<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/v1/auth/send-otp + /resend-otp. Mirrors the
 * OpenAPI `OtpSendRequest` schema.
 *
 * @bodyParam phone string required Qatari phone in `+974XXXXXXXX` shape. Example: +97455123456
 */
class OtpSendRequest extends FormRequest
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
            'phone' => [
                'required',
                'string',
                'regex:' . config('qbazaar.phone_regex'),
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
        ];
    }
}
