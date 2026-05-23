<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/v1/auth/reset-password. The password rule mirrors
 * RegisterRequest so we keep the "uppercase + lowercase + digit + symbol"
 * policy in one place — both endpoints accept the same kind of password.
 */
class ResetPasswordRequest extends FormRequest
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
        $minLength = (int) config('qbazaar.auth.password_min_length', 8);

        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:' . $minLength,
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.regex' => 'The password must contain uppercase, lowercase, a number and a symbol.',
        ];
    }
}
