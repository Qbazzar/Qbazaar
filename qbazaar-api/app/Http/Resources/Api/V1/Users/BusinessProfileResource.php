<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Users;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Business details of a business account. The owner sees everything; the
 * public view hides the contact phone and email unless the owner's privacy
 * settings show them. Expects `businessProfile` and the cover media loaded.
 *
 * @property User $resource
 */
class BusinessProfileResource extends JsonResource
{
    public function __construct(
        User $owner,
        private readonly bool $publicView,
    ) {
        parent::__construct($owner);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owner = $this->resource;
        $profile = $owner->businessProfile;
        $privacy = $owner->privacySettings();

        return [
            'business_name' => $profile?->business_name,
            'about' => $profile?->about,
            'legal_name' => $profile?->legal_name,
            'commercial_registration_number' => $profile?->commercial_registration_number,
            'contact_phone' => $this->publicView && ! $privacy->show_phone ? null : $profile?->contact_phone,
            'contact_email' => $this->publicView && ! $privacy->show_email ? null : $profile?->contact_email,
            'website' => $profile?->website,
            'address' => $profile?->address,
            'opening_hours' => $profile->opening_hours ?? [],
            'cover_url' => $owner->businessCoverUrl(),
            'updated_at' => $profile?->updated_at->toIso8601String(),
        ];
    }
}
