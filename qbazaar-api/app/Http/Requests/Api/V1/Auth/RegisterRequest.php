<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Enums\AccountType;
use App\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirror of the OpenAPI `RegisterRequest` schema in qbazaar-contracts/openapi/v1.yaml.
     *
     * Strong-password rule (uppercase + lowercase + number + symbol) is enforced
     * via regex because the contract says so. The Qatari phone format
     * `^\+974[0-9]{8}$` is pulled from config so it stays in one place.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $minLength = (int) config('qbazaar.auth.password_min_length', 8);

        return [
            'full_name' => ['required', 'string', 'min:3', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => [
                'required',
                'string',
                'regex:' . config('qbazaar.phone_regex'),
                'unique:users,phone',
            ],
            'password' => [
                'required',
                'string',
                'min:' . $minLength,
                // uppercase + lowercase + digit + symbol
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
            ],
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number must be a valid Qatari number (+974XXXXXXXX).',
            'password.regex' => 'The password must contain uppercase, lowercase, a number and a symbol.',
        ];
    }
}
