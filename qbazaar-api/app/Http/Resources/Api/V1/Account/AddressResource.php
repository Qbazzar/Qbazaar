<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserAddress
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'supplement' => $this->supplement,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'location_id' => $this->location_id,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
