<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Home;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact seller card for the home screen. Contact details are left out on
 * purpose: they follow the privacy settings on the public profile only.
 *
 * @mixin User
 */
class SellerCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'avatar_url' => $this->avatar_url,
            'account_type' => $this->account_type->value,
            'ads_count' => (int) $this->resource->getAttribute('listed_ads_count'),
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
        ];
    }
}
