<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

use App\Enums\SlaState;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Measures how long an item has waited against a response target: on time,
 * due soon (past the warning share of the target) or overdue.
 */
final readonly class SlaClock
{
    public function __construct(
        public int $targetHours,
        public float $warnRatio,
        public CarbonImmutable $now,
    ) {}

    public static function fromConfig(?CarbonImmutable $now = null): self
    {
        return new self(
            max(1, (int) config('qbazaar.admin.moderation_sla_hours')),
            min(1.0, max(0.0, (float) config('qbazaar.admin.moderation_sla_warn_ratio'))),
            $now ?? CarbonImmutable::now(),
        );
    }

    public function stateFor(CarbonInterface $since): SlaState
    {
        $waitedMinutes = $this->waitedMinutes($since);
        $targetMinutes = $this->targetHours * 60;

        return match (true) {
            $waitedMinutes >= $targetMinutes => SlaState::OVERDUE,
            $waitedMinutes >= $targetMinutes * $this->warnRatio => SlaState::DUE_SOON,
            default => SlaState::ON_TIME,
        };
    }

    /** Items submitted at or before this moment are overdue. */
    public function overdueBefore(): CarbonImmutable
    {
        return $this->now->subHours($this->targetHours);
    }

    public function waitedMinutes(CarbonInterface $since): int
    {
        return max(0, (int) floor(($this->now->getTimestamp() - $since->getTimestamp()) / 60));
    }
}
