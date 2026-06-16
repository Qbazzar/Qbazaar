<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Device-token registration payload.
 *
 * `platform` is optional and defaults to `web` in the controller — the MVP
 * only ships web push, but the column is sized for the mobile apps so the
 * contract accepts their values from day one.
 *
 * @bodyParam token string required FCM registration token for this browser/device. Example: fcm-registration-token
 * @bodyParam platform string Platform the token belongs to (web|android|ios). Defaults to web. Example: web
 */
class RegisterDeviceTokenRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['sometimes', Rule::in(['web', 'android', 'ios'])],
        ];
    }
}
