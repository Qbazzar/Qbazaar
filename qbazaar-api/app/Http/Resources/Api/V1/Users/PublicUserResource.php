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
 * `business_name` is only populated when the account type is business.
 * `ads_count` reflects the user's currently-active (public) listings.
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
            'business_name' => $this->account_type === AccountType::BUSINESS ? $this->full_name : null,
            'joined_at' => $this->created_at->toIso8601String(),
            'verification_badges' => [
                'email_verified' => (bool) $this->email_verified,
                'phone_verified' => (bool) $this->phone_verified,
                'business_verified' => false, // TODO Phase 2
            ],
            'ads_count' => $this->resource->ads()->active()->count(),
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
        ];

        if ($privacy->show_phone) {
            $payload['phone'] = $this->phone;
        }

        if ($privacy->show_email) {
            $payload['email'] = $this->email;
        }

        return $payload;
    }
}
