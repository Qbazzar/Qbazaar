<?php

declare(strict_types=1);

namespace App\Http\Requests\Manage;

use Illuminate\Foundation\Http\FormRequest;

class ImpersonateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.impersonate') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:' . (int) config('qbazaar.admin.impersonation_reason_min_length'),
                'max:' . (int) config('qbazaar.admin.impersonation_reason_max_length'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => (string) __('admin.impersonation.reason')];
    }
}
