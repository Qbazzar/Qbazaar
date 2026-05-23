<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates POST /api/v1/auth/reset-password. The password rule mirrors
 * RegisterRequest so we keep the "uppercase + lowercase + digit + symbol"
 * policy in one place — both endpoints accept the same kind of password.
 *
 * @bodyParam email string required The email address the reset link was sent to. Example: user@example.qa
 * @bodyParam token string required The reset token from the email. Example: a1b2c3d4...
 * @bodyParam password string required New password (must include uppercase, lowercase, digit, symbol). Example: New!Pass5678
 * @bodyParam password_confirmation string required Must equal `password`. Example: New!Pass5678
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
