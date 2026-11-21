<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Data\Account\StepUpProof;
use App\Http\Requests\Api\V1\Account\Concerns\ConfirmsWithPasswordOrCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Change-password payload for an authenticated user.
 *
 * The change is confirmed even though the user is already authenticated —
 * defence in depth against session-hijack / token-theft — with exactly one
 * of `current_password` or `reauth_code`. Accounts without a password must
 * use the code; this is how they set their first password.
 *
 * @bodyParam current_password string The current account password; omit when sending reauth_code. Example: Str0ng!Pass
 * @bodyParam reauth_code string Code from POST /account/reauth-code; required for accounts without a password. Example: 123456
 * @bodyParam password string required New password. Example: NewStr0ng!Pass
 * @bodyParam password_confirmation string required Must match `password`. Example: NewStr0ng!Pass
 */
class UpdatePasswordRequest extends FormRequest
{
    use ConfirmsWithPasswordOrCode;

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
            ...$this->stepUpRules('current_password'),
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
                'different:current_password',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->stepUpMessages('current_password');
    }

    public function stepUpProof(): StepUpProof
    {
        return $this->stepUpProofFrom('current_password');
    }
}
