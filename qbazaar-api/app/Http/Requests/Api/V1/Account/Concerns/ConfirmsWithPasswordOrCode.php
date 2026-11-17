<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account\Concerns;

use App\Data\Account\StepUpProof;
use App\Models\User;

/**
 * Step-up confirmation for sensitive account changes: exactly one of the
 * current password or the code emailed by POST /account/reauth-code.
 * Accounts without a password (email-code or social sign-up) must use the
 * code, so their rules prohibit the password field outright.
 *
 * Only the shape is validated here; the code is spent later, once every
 * other field of the request is valid.
 */
trait ConfirmsWithPasswordOrCode
{
    /**
     * @return array<string, list<string>>
     */
    protected function stepUpRules(string $passwordField): array
    {
        // nullable: clients that send the unused field as null mean "absent".
        $codeRules = ['nullable', 'string', 'regex:/^[0-9]{6}$/'];

        if (! $this->accountHasPassword()) {
            return [
                $passwordField => ['prohibited'],
                'reauth_code' => ['required', ...$codeRules],
            ];
        }

        return [
            $passwordField => ['required_without:reauth_code', 'prohibits:reauth_code', 'nullable', 'string'],
            'reauth_code' => ['required_without:' . $passwordField, ...$codeRules],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function stepUpMessages(string $passwordField): array
    {
        return [
            $passwordField . '.prohibited' => (string) __('users.step_up.no_password'),
            $passwordField . '.required_without' => (string) __('users.step_up.required'),
            $passwordField . '.prohibits' => (string) __('users.step_up.only_one'),
            'reauth_code.required' => (string) __('users.step_up.code_required'),
            'reauth_code.required_without' => (string) __('users.step_up.required'),
        ];
    }

    protected function stepUpProofFrom(string $passwordField): StepUpProof
    {
        $code = $this->validated('reauth_code');

        return is_string($code)
            ? StepUpProof::reauthCode($code)
            : StepUpProof::password((string) $this->validated($passwordField));
    }

    private function accountHasPassword(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPassword();
    }
}
