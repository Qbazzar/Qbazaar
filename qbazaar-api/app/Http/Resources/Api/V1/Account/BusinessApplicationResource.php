<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use App\Enums\BusinessApplicationStatus;
use App\Models\BusinessApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The signed-in user's business application. Documents are listed without
 * links: they are legal papers on a private disk that only reviewing staff
 * open. `review_note` is the reason or the request staff sent with their
 * decision; `can_resubmit` is true while staff wait for more information.
 * Expects `media` loaded.
 *
 * @mixin BusinessApplication
 */
class BusinessApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'business_name' => $this->business_name,
            'legal_name' => $this->legal_name,
            'commercial_registration_number' => $this->commercial_registration_number,
            'applicant_note' => $this->applicant_note,
            'review_note' => $this->review_note,
            'can_resubmit' => $this->status === BusinessApplicationStatus::NEEDS_INFO,
            'documents' => $this->resource->media
                ->map(static fn (Media $document): array => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'mime_type' => $document->mime_type,
                    'size' => (int) $document->size,
                ])
                ->values()
                ->all(),
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
