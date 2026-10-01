<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam email string required The new address; must not belong to any account, including the caller's. Example: new@example.qa
 * @bodyParam reauth_code string required Code from POST /account/reauth-code. Example: 123456
 */
class ChangeEmailRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'reauth_code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ];
    }
}
