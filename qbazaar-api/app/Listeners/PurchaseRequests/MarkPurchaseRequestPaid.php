<?php

declare(strict_types=1);

namespace App\Listeners\PurchaseRequests;

use App\Actions\PurchaseRequests\MarkPurchaseRequestPaidAction;
use App\Enums\OrderSource;
use App\Enums\QueueName;
use App\Events\Orders\OrderCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A completed order placed from a "Buy Now" request turns its card to paid.
 */
class MarkPurchaseRequestPaid implements ShouldQueue
{
    public string $queue = QueueName::DEFAULT->value;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public int $timeout = 30;

    public function __construct(private readonly MarkPurchaseRequestPaidAction $markPaid) {}

    public function shouldQueue(OrderCompleted $event): bool
    {
        return $event->order->source === OrderSource::PURCHASE_REQUEST;
    }

    public function handle(OrderCompleted $event): void
    {
        ($this->markPaid)($event->order);
    }
}
