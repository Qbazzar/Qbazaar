<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload for scheduling account deletion.
 *
 * Same shape as deactivation (password + optional reason) but a totally
 * separate request class so future divergence — e.g. requiring a typed
 * confirmation string "DELETE" — only lands here.
 *
 * @bodyParam password string required Current account password. Example: Str0ng!Pass
 * @bodyParam reason string Optional reason. Example: Found another platform.
 */
class DeleteAccountRequest extends FormRequest
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
