<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * The stock database channel, plus the payload's `category` copied into
 * its own indexed column for the inbox filter.
 */
class CategorizedDatabaseChannel extends DatabaseChannel
{
    private const CATEGORY_LENGTH = 64;

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification)
    {
        $payload = parent::buildPayload($notifiable, $notification);
        $category = $payload['data']['category'] ?? null;

        $payload['category'] = is_string($category) ? mb_substr($category, 0, self::CATEGORY_LENGTH) : null;

        return $payload;
    }
}
