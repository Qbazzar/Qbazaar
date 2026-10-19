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
 * Notifies one chunk of a broadcast's audience. Recipients are claimed per
 * broadcast, so a retry only reaches the ones not notified yet.
 */
class DeliverBroadcastChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 120;

    /**
     * @param list<string> $userIds
     */
    public function __construct(
        public readonly string $broadcastId,
        public readonly array $userIds,
    ) {
        $this->onQueue(QueueName::NOTIFICATIONS);
    }

    public function handle(BroadcastDelivery $delivery): void
    {
        $broadcast = StaffBroadcast::query()->find($this->broadcastId);

        if ($broadcast !== null) {
            $delivery->deliverChunk($broadcast, $this->userIds);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(BroadcastDelivery::class)->markFailed($this->broadcastId);
    }
}
