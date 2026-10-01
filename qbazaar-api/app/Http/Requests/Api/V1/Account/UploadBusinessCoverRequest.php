<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Multipart body for `POST /api/v1/account/business-profile/cover`.
 */
class UploadBusinessCoverRequest extends FormRequest
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
            'cover' => [
                'required',
                'file',
                'image',
                'max:' . (int) config('qbazaar.social.max_cover_size_kb'),
                'mimetypes:' . implode(',', (array) config('qbazaar.uploads.allowed_mime_types')),
            ],
        ];
    }
}
