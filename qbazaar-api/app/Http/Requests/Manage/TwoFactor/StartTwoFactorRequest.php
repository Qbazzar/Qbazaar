<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage\TwoFactor;

use App\Services\Admin\TwoFactor\EnrolmentPasswordConfirmation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Starting (or restarting) the two-factor setup asks for the password,
 * unless this session confirmed it a few minutes ago for the same setup.
 */
class StartTwoFactorRequest extends FormRequest
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
        if ($this->confirmation()->isRecent($this->session())) {
            return [];
        }

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

    protected function passedValidation(): void
    {
        // Only a password this request checked opens the window.
        if ($this->safe()->has('current_password')) {
            $this->confirmation()->confirm($this->session());
        }
    }

    private function confirmation(): EnrolmentPasswordConfirmation
    {
        return $this->container->make(EnrolmentPasswordConfirmation::class);
    }
}
