<?php

declare(strict_types=1);

namespace App\Casts;

use App\Data\Account\NotificationPreferences;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts `users.notification_preferences` to the value object. A null column
 * reads as the defaults, so callers never deal with a missing row.
 *
 * @implements CastsAttributes<NotificationPreferences, NotificationPreferences|null>
 */
class AsNotificationPreferences implements CastsAttributes
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): NotificationPreferences
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return NotificationPreferences::fromArray(is_array($decoded) ? $decoded : []);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof NotificationPreferences) {
            throw new InvalidArgumentException('notification_preferences expects a NotificationPreferences instance.');
        }

        return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
    }
}
