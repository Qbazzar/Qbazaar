<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Ads;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for `POST /api/v1/ads/{ad}/images/reorder`.
 *
 * Expects a complete ordered list of media IDs belonging to the ad. The
 * controller checks each ID actually belongs to the ad before invoking
 * `Media::setNewOrder()` — that prevents reordering by ID-stuffing.
 *
 * Spatie MediaLibrary stores media on a bigint PK (the morph target is the
 * ULID-keyed owner, not the media row itself), so the IDs here are integers.
 *
 * @bodyParam order int[] required Media row IDs in desired order.
 */
class ReorderImagesRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'exists:media,id'],
        ];
    }
}
