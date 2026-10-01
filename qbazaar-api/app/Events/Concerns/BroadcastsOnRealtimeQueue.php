<?php

declare(strict_types=1);

namespace App\Events\Concerns;

use App\Enums\QueueName;

/**
 * Laravel copies these onto the BroadcastEvent job it queues for the event.
 * A broadcast still failing after a few retries is stale, so it gives up
 * quickly instead of delivering an outdated frame.
 */
trait BroadcastsOnRealtimeQueue
{
    public int $tries = 3;

    public int $timeout = 15;

    /** @var list<int> */
    public array $backoff = [1, 5, 15];

    public function broadcastQueue(): string
    {
        return QueueName::REALTIME->value;
    }
}
