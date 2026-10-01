<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam challenge_token string required The token from the 202 sign-in response.
 * @bodyParam code string required The 6-digit SMS code. Example: 482915
 */
class DeviceVerifyRequest extends FormRequest
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
            'challenge_token' => ['required', 'string', 'size:64'],
            'code' => ['required', 'string', 'regex:/^[0-9]{' . $length . '}$/'],
        ];
    }
}
