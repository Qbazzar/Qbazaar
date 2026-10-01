<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Profile update payload for both PUT (every field) and PATCH (any subset).
 *
 * Deliberately narrow: email / phone changes go through their own verified
 * flows, so this request only accepts the freely-editable fields.
 *
 * @bodyParam full_name string Updated display name; required on PUT. Example: Ahmed Al-Ali
 * @bodyParam language string UI language preference; required on PUT. Example: ar
 */
class UpdateProfileRequest extends FormRequest
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
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'full_name' => [$presence, 'string', 'min:3', 'max:80'],
            'language' => [$presence, 'string', new Enum(Language::class)],
        ];
    }
}
