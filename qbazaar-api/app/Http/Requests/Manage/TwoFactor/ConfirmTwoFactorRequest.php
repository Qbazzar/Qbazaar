<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\TwoFactor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The first authenticator code, proving the app holds the new secret.
 * Any signed-in staff member manages their own two-factor sign-in.
 */
class ConfirmTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:16'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => (string) __('admin.two_factor.fields.code')];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }
}
