<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use App\Data\Account\NotificationPreferences;
use App\Enums\NotificationPreferenceChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property NotificationPreferences $resource
 */
class NotificationPreferencesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'email' => $this->resource->for(NotificationPreferenceChannel::EMAIL),
            'push' => $this->resource->for(NotificationPreferenceChannel::PUSH),
        ];
    }
}
