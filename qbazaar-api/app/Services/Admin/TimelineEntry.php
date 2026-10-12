<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Carbon\CarbonImmutable;

/**
 * One row of an admin side-column timeline (audit trail or status history).
 */
final readonly class TimelineEntry
{
    public const TONES = ['neutral', 'success', 'warning', 'danger'];

    public function __construct(
        public string $title,
        public ?string $actor,
        public CarbonImmutable $at,
        public string $tone = 'neutral',
    ) {}
}
