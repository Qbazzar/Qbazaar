<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a single multipart-form file upload for the user's avatar.
 *
 * Limits:
 *  - MIME must be one of jpeg / png / webp (the platform-wide image set).
 *  - Max size 5 MB — small enough to be cheap to handle on mobile uploads,
 *    big enough not to reject reasonable phone-camera output.
 *
 * Magic-bytes verification will be added in Sprint 4 alongside the wider
 * uploads pipeline; for avatars we lean on Laravel's `image` rule which
 * already does a minimal sanity check (PHP getimagesize).
 *
 * @bodyParam avatar file required Image file. Example: avatar.png
 */
class UploadAvatarRequest extends FormRequest
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
            'avatar' => [
                'required',
                'file',
                'image',
                'max:' . (int) config('qbazaar.uploads.max_avatar_size_kb'),
                'mimetypes:' . implode(',', config('qbazaar.uploads.allowed_mime_types')),
            ],
        ];
    }
}
