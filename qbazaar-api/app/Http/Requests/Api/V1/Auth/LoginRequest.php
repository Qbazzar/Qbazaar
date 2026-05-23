<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam identifier string required Either the user's email OR Qatari phone (+974XXXXXXXX). Example: ahmed@example.qa
 * @bodyParam password string required The account password. Example: Str0ng!Pass
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `identifier` is either an email or a Qatari phone — we let the controller
     * disambiguate so we keep the same single field on the wire as in the spec.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
