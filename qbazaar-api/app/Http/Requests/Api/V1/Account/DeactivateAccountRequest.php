<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deactivation payload. We require the current password (defence in depth
 * against session hijack — even if an attacker has the bearer token, they
 * shouldn't be able to lock the legitimate owner out by deactivating the
 * account without knowing the password).
 *
 * `reason` is optional free-text; we surface it in the activity log so
 * Customer Success can spot recurring complaints when reviewing the audit.
 *
 * @bodyParam password string required The current account password. Example: Str0ng!Pass
 * @bodyParam reason string Optional reason — 500 char cap. Example: I'm taking a break.
 */
class DeactivateAccountRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
