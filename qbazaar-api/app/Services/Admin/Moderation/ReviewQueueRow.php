<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

use App\Enums\SlaState;
use App\Models\Ad;
use Carbon\CarbonInterface;

/**
 * One pending ad in the review queue, with how long it has waited and where
 * that stands against the SLA, so the view only renders.
 */
final readonly class ReviewQueueRow
{
    /**
     * @param list<string> $flags auto-moderation findings, already labelled; empty when clean or not run yet
     */
    public function __construct(
        public Ad $ad,
        public CarbonInterface $since,
        public SlaState $sla,
        public string $waited,
        public ?bool $clean,
        public array $flags,
    ) {}

    public function isOverdue(): bool
    {
        return $this->sla === SlaState::OVERDUE;
    }
}
