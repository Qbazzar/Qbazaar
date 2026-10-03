<?php

declare(strict_types=1);

namespace App\Jobs\Orders;

use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Models\Order;
use App\Services\Orders\OrderDisputeWindow;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hourly sweep for escrow orders whose release window has passed since the
 * handover with neither a confirmation nor a dispute from the buyer. It
 * only reads ids and queues {@see ReleaseEscrowOrdersBatchJob} batches;
 * unique while queued or running so runs never overlap.
 *
 * Cash orders never wait here: the seller's confirmation completes them.
 * With no escrow gateway configured (before M7) the sweep does nothing.
 */
class ReleaseDueEscrowOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(OrderDisputeWindow $window): void
    {
        $escrowMethods = $window->escrowMethods();

        if ($escrowMethods === []) {
            return;
        }

        Order::query()
            ->where('status', OrderStatus::AWAITING_HANDOVER->value)
            ->where('handed_over_at', '<=', $window->releaseCutoff())
            ->whereIn('payment_method', $escrowMethods)
            ->select('id')
            ->chunkById((int) config('qbazaar.sweeps.batch_size'), function (Collection $orders): void {
                /** @var list<string> $ids */
                $ids = $orders->modelKeys();
                ReleaseEscrowOrdersBatchJob::dispatch($ids);
            });
    }
}
