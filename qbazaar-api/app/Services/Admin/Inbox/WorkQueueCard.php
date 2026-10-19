<?php

declare(strict_types=1);

namespace App\Services\Admin\Inbox;

use App\Enums\SlaState;

/**
 * One work queue on the inbox: how many items wait, how long the oldest has
 * waited and where that stands against the review target.
 */
final readonly class WorkQueueCard
{
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public int $count,
        public string $href,
        public ?string $oldestWaited,
        public ?SlaState $state,
        public ?int $overdue,
    ) {}
}
