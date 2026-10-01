<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam phone string required The new Qatari number; must not belong to any account. Example: +97455123456
 * @bodyParam reauth_code string required Code from POST /account/reauth-code. Example: 123456
 */
class ChangePhoneRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:' . config('qbazaar.phone_regex'), 'unique:users,phone'],
            'reauth_code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ];
    }
}
