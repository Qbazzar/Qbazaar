<?php

declare(strict_types=1);

namespace App\Jobs\Orders;

use App\Data\Ledger\LedgerActor;
use App\Enums\OrderStatus;
use App\Enums\QueueName;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Services\Orders\OrderDisputeWindow;
use App\Services\Orders\OrderTransitionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Completes one batch queued by {@see ReleaseDueEscrowOrdersJob}, which
 * releases each order's escrow to the seller through its gateway's
 * settleHandover. The completion re-reads the order under its lock, so an
 * order the buyer disputed meanwhile is skipped and a retried batch posts
 * nothing twice.
 */
class ReleaseEscrowOrdersBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 120;

    /**
     * @param list<string> $orderIds
     */
    public function __construct(public readonly array $orderIds)
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(OrderTransitionService $transitions, OrderDisputeWindow $window): void
    {
        $orders = Order::query()
            ->whereKey($this->orderIds)
            ->where('status', OrderStatus::AWAITING_HANDOVER->value)
            ->where('handed_over_at', '<=', $window->releaseCutoff())
            ->get();

        foreach ($orders as $order) {
            try {
                $transitions->complete($order, LedgerActor::system());
            } catch (DomainException $exception) {
                Log::info('Escrow release skipped.', ['order_id' => $order->id, 'error' => $exception->errorCode->value]);
            }
        }
    }
}
