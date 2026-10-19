<?php

declare(strict_types=1);

namespace App\Jobs\Broadcasts;

use App\Enums\QueueName;
use App\Models\StaffBroadcast;
use App\Services\Admin\Broadcasts\BroadcastDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Splits a staff broadcast's audience into delayed delivery chunks. Not
 * retried: a broadcast that is already sending is never fanned out twice,
 * so a failure is reported on the history page instead.
 *
 * The fan-out only reads user ids and queues chunk jobs (one keyset query
 * per 500 accounts), so it stays well inside a timeout below the
 * notifications queue's retry_after.
 */
class FanOutBroadcastJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** A single try leaves no retry to wait for. */
    public int $backoff = 0;

    public int $timeout = 120;

    public function __construct(public readonly string $broadcastId)
    {
        $this->onQueue(QueueName::NOTIFICATIONS);
    }

    public function handle(BroadcastDelivery $delivery): void
    {
        $broadcast = StaffBroadcast::query()->find($this->broadcastId);

        if ($broadcast !== null) {
            $delivery->fanOut($broadcast);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(BroadcastDelivery::class)->markFailed($this->broadcastId);
    }
}
