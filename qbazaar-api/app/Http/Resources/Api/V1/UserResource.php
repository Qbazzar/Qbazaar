<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Mirror of the OpenAPI `User` schema — keep field names and order in sync
     * with qbazaar-contracts/openapi/v1.yaml.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'account_type' => $this->account_type->value,
            'status' => $this->status->value,
            'email_verified' => (bool) $this->email_verified,
            'phone_verified' => (bool) $this->phone_verified,
            'language' => $this->language->value,
            'avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
