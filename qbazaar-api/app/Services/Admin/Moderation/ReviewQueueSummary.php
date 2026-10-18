<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

use App\Enums\SlaState;
use Carbon\CarbonInterface;

/**
 * Headline figures above the review queue: how many ads wait, how many are
 * past the SLA and how long the oldest has waited.
 */
final readonly class ReviewQueueSummary
{
    public function __construct(
        public int $waiting,
        public int $overdue,
        public ?CarbonInterface $oldestSince,
        public ?string $oldestWaited,
        public ?SlaState $oldestState,
        public int $targetHours,
    ) {}
}
