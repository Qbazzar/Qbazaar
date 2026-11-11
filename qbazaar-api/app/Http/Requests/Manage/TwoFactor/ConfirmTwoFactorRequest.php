<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\TwoFactor;

use App\Services\Admin\TwoFactor\EnrolmentPasswordConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The first authenticator code, proving the app holds the new secret.
 * Any signed-in staff member manages their own two-factor sign-in, within
 * the window opened by the password that started the setup.
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
     * The error sits on the password field, which the page shows again once
     * the window has closed.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->ensurePasswordStillConfirmed($validator)];
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

    private function ensurePasswordStillConfirmed(Validator $validator): void
    {
        if (! $this->container->make(EnrolmentPasswordConfirmation::class)->isRecent($this->session())) {
            $validator->errors()->add('current_password', (string) __('admin.two_factor.errors.password_expired'));
        }
    }
}
