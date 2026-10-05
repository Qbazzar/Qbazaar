<?php

declare(strict_types=1);

namespace App\Jobs\Promotions;

use App\Enums\PromotionStatus;
use App\Enums\QueueName;
use App\Models\AdPromotion;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sweep for active promotions whose time is up. It only reads ids and
 * queues {@see ExpirePromotionsBatchJob} per `qbazaar.sweeps.batch_size`
 * promotions; unique while queued or running so runs never overlap.
 *
 * Scheduled every `qbazaar.promotions.expire_every_minutes` in bootstrap/app.php.
 */
class ExpirePromotionsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onQueue(QueueName::LOW);
    }

    public function handle(): void
    {
        AdPromotion::query()
            ->where('status', PromotionStatus::ACTIVE->value)
            ->where('ends_at', '<=', now())
            ->select('id')
            ->chunkById((int) config('qbazaar.sweeps.batch_size'), function (Collection $promotions): void {
                /** @var list<string> $ids */
                $ids = $promotions->modelKeys();
                ExpirePromotionsBatchJob::dispatch($ids);
            });
    }
}
