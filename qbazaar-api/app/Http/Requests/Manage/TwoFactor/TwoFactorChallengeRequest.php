<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\TwoFactor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The code prompt after the password: an authenticator code, or one of the
 * recovery codes when the phone is not at hand.
 */
class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reached before sign-in; the pending session is checked by the controller.
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:32'],
            'trust_browser' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => (string) __('admin.two_factor.fields.code'),
            'recovery_code' => (string) __('admin.two_factor.fields.recovery_code'),
        ];
    }

    public function usesRecoveryCode(): bool
    {
        return filled($this->validated('recovery_code'));
    }

    public function submittedCode(): string
    {
        return (string) ($this->usesRecoveryCode() ? $this->validated('recovery_code') : $this->validated('code'));
    }

    public function trustBrowser(): bool
    {
        return $this->boolean('trust_browser');
    }
}
