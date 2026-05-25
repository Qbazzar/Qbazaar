<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Public shape of a single in-app notification.
 *
 * The DB row stores a free-form JSON payload via Laravel's standard
 * Notification facility; we project a stable subset of fields here so the
 * FE never has to introspect the raw payload. Anything else stays in
 * `data` for forward-compatibility — new notifications can ship without a
 * resource change.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $data['category'] ?? null,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'cta_url' => $data['cta_url'] ?? null,
            'icon' => $data['icon'] ?? null,
            'data' => $data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
