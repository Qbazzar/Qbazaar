<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth\Concerns;

use App\Enums\AccountType;
use App\Enums\Language;
use Illuminate\Validation\Rule;

/**
 * Passwordless and social sign-in double as sign-up: the client sends the
 * new account's details only when the server answered AUTH_011. Sending any
 * one of them makes the whole set required.
 */
trait AcceptsRegistrationDetails
{
    /**
     * @return array<string, mixed>
     */
    protected function registrationRules(): array
    {
        if (! $this->isRegistering()) {
            return [];
        }

        return [
            'full_name' => ['required', 'string', 'min:3', 'max:80'],
            'phone' => ['required', 'string', 'regex:' . config('qbazaar.phone_regex'), 'unique:users,phone'],
            'account_type' => ['required', Rule::in([
                AccountType::PRIVATE_INDIVIDUAL->value,
                AccountType::BUSINESS->value,
            ])],
            'language' => ['sometimes', Rule::in([
                Language::ARABIC->value,
                Language::ENGLISH->value,
            ])],
            'accepted_terms' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array{full_name: string, phone: string, account_type: string, language?: string}|null
     */
    public function registrationDetails(): ?array
    {
        if (! $this->isRegistering()) {
            return null;
        }

        /** @var array{full_name: string, phone: string, account_type: string, language?: string} $details */
        $details = $this->safe()->only(['full_name', 'phone', 'account_type', 'language']);

        return $details;
    }

    private function isRegistering(): bool
    {
        return $this->hasAny(['full_name', 'phone', 'account_type', 'accepted_terms']);
    }
}
