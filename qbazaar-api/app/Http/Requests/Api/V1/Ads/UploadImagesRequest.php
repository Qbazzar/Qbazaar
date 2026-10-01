<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use App\Enums\PlatformSetting;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Multipart body for `POST /api/v1/ads/{ad}/images`.
 *
 * A single request carries at most `qbazaar.ads.max_images_per_upload`
 * files so its body stays within the web server limits documented in
 * deploy/README.md. The per-ad total (platform setting `ad_max_images`) is
 * enforced under a lock by AttachAdImagesAction.
 *
 * The MIME allowlist (`qbazaar.uploads.allowed_mime_types`) is checked
 * against the sniffed content type, never the client-supplied filename.
 */
class UploadImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(SettingsService $settings): array
    {
        $perRequest = min(
            (int) config('qbazaar.ads.max_images_per_upload'),
            $settings->integer(PlatformSetting::AD_MAX_IMAGES),
        );

        return [
            'images' => ['required', 'array', 'min:1', "max:{$perRequest}"],
            'images.*' => [
                'file',
                'image',
                'mimetypes:' . implode(',', config('qbazaar.uploads.allowed_mime_types')),
                'max:' . (int) config('qbazaar.uploads.max_image_size_kb'),
            ],
        ];
    }
}
