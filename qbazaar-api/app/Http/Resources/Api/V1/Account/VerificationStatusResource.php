<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Verification snapshot for the signed-in user.
 *
 * `business_verified` is the verified-business badge staff grant (directly
 * or by approving a business application, see GET /account/business-application).
 * `kyc_status` is still a placeholder: the KYC workflow ships in Phase 2, and
 * returning the field now keeps the response contract stable.
 *
 * @mixin User
 */
class VerificationStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'email_verified' => (bool) $this->email_verified,
            'phone_verified' => (bool) $this->phone_verified,
            'business_verified' => $this->resource->isVerifiedBusiness(),
            'kyc_status' => 'none',       // TODO Phase 2: wire to kyc_status enum on users
        ];
    }
}
