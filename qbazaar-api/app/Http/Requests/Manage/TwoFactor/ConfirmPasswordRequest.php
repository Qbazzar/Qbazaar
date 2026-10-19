<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\TwoFactor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Re-entering the password before turning two-factor sign-in off or
 * replacing the recovery codes, so an unattended session cannot do either.
 */
class ConfirmPasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['current_password' => (string) __('admin.two_factor.fields.current_password')];
    }
}
