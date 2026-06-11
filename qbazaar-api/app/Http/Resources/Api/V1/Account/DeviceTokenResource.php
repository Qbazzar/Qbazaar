<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public shape of a registered device token.
 *
 * Deliberately omits the raw FCM token — the client already holds it, and
 * echoing it back would only widen the surface a leaked response exposes.
 *
 * @property DeviceToken $resource
 */
class DeviceTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'platform' => $this->resource->platform,
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
