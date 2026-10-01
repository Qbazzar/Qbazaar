<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\Concerns\AcceptsRegistrationDetails;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam id_token string required The OpenID Connect id_token from Google / Apple sign-in.
 * @bodyParam full_name string Sign-up only. Example: Ahmed Al-Ali
 * @bodyParam phone string Sign-up only, +974XXXXXXXX. Example: +97455123456
 * @bodyParam account_type string Sign-up only, `private` or `business`. Example: private
 * @bodyParam language string Sign-up only, `ar` or `en`. Example: ar
 * @bodyParam accepted_terms boolean Sign-up only, must be `true`. Example: true
 */
class SocialSignInRequest extends FormRequest
{
    use AcceptsRegistrationDetails;

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
            'id_token' => ['required', 'string', 'max:8192'],
            ...$this->registrationRules(),
        ];
    }
}
