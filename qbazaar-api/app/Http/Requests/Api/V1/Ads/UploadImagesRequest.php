<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Multipart body for `POST /api/v1/ads/{ad}/images`.
 *
 * Limits (per the product spec):
 *  - 1..10 files per request
 *  - 10 MB cap per file
 *  - jpg/jpeg/png/webp only
 *  - existing + new image count must stay ≤ 10 (config: `qbazaar.ads.max_images`)
 *
 * Per-file MIME enforcement uses the `mimes:` rule which leans on the real
 * uploaded MIME type, not the filename — magic-bytes verification stays in
 * the Sprint 4 uploads pipeline (which we'll layer on top later).
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
    public function rules(): array
    {
        $max = (int) config('qbazaar.ads.max_images', 10);

        return [
            'images' => ['required', 'array', 'min:1', "max:{$max}"],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240', // 10 MB in kB
            ],
        ];
    }

    /**
     * Cross-field validation — enforce the total cap (existing + incoming).
     * Implemented as an `after` callback so we read the ad from the route
     * binding without coupling the rules() array to the database.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ad = $this->route('ad');
            if (! $ad instanceof Ad) {
                $ad = Ad::query()->find($this->route('ad'));
            }

            if (! $ad instanceof Ad) {
                return; // 404 surfaces later in the controller.
            }

            $existing = $ad->getMedia('images')->count();
            $incoming = is_array($this->file('images')) ? count((array) $this->file('images')) : 0;
            $max = (int) config('qbazaar.ads.max_images', 10);

            if (($existing + $incoming) > $max) {
                throw new DomainException(
                    ErrorCode::UPLOAD_MAX_IMAGES_REACHED,
                    __('errors.ad.images.too_many', ['max' => $max]),
                    ['existing' => $existing, 'incoming' => $incoming, 'max' => $max],
                );
            }
        });
    }
}
