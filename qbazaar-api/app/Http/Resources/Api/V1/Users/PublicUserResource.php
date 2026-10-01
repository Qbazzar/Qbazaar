<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Users;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Read-only profile shape returned by GET /users/{user}/public-profile.
 *
 * Honours the target user's privacy settings:
 *  - `phone` is omitted unless `show_phone` is true (default true)
 *  - `email` is omitted unless `show_email` is true (default false)
 *
 * `business_name` is only populated when the account type is business; it
 * comes from the business profile when the caller loaded it.
 * `ads_count` is the stored count of the user's ACTIVE ads.
 *
 * @mixin User
 */
class PublicUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $privacy = $this->resource->privacySettings();

        $payload = [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'avatar_url' => $this->avatar_url,
            'account_type' => $this->account_type->value,
            'business_name' => $this->account_type === AccountType::BUSINESS ? $this->businessName() : null,
            'joined_at' => $this->created_at->toIso8601String(),
            'verification_badges' => [
                'email_verified' => (bool) $this->email_verified,
                'phone_verified' => (bool) $this->phone_verified,
                'business_verified' => false, // TODO Phase 2
            ],
            'ads_count' => (int) $this->active_ads_count,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
            'followers_count' => (int) $this->followers_count,
            'following_count' => (int) $this->following_count,
        ];

        if ($privacy->show_phone) {
            $payload['phone'] = $this->phone;
        }

        if ($privacy->show_email) {
            $payload['email'] = $this->email;
        }

        return $payload;
    }

    private function businessName(): string
    {
        $profileName = $this->resource->relationLoaded('businessProfile')
            ? $this->resource->businessProfile?->business_name
            : null;

        return $profileName ?? $this->full_name;
    }
}
