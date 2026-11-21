<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Data\Account\StepUpProof;
use App\Http\Requests\Api\V1\Account\Concerns\ConfirmsWithPasswordOrCode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for scheduling account deletion.
 *
 * Confirmed with exactly one of `password` or `reauth_code` (accounts without
 * a password must use the code), plus an optional reason. A separate request
 * class from deactivation so future divergence — e.g. requiring a typed
 * confirmation string "DELETE" — only lands here.
 *
 * @bodyParam password string Current account password; omit when sending reauth_code. Example: Str0ng!Pass
 * @bodyParam reauth_code string Code from POST /account/reauth-code; required for accounts without a password. Example: 123456
 * @bodyParam reason string Optional reason. Example: Found another platform.
 */
class DeleteAccountRequest extends FormRequest
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
            ...$this->stepUpRules('password'),
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->stepUpMessages('password');
    }

    public function stepUpProof(): StepUpProof
    {
        return $this->stepUpProofFrom('password');
    }
}
