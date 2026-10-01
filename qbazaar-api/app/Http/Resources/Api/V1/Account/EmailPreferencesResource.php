<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Account;

use App\Data\Account\NotificationPreferences;
use App\Enums\NotificationPreferenceChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The email half of the notification preferences, one boolean per topic.
 *
 * @property NotificationPreferences $resource
 */
class EmailPreferencesResource extends JsonResource
{
    /**
     * @return array<string, bool>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->for(NotificationPreferenceChannel::EMAIL);
    }
}
