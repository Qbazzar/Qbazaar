<?php

declare(strict_types=1);

namespace App\Data\Admin\AdDeal;

use Carbon\CarbonImmutable;

/**
 * One stop of the order tracker. `state` is done, current, upcoming, failed
 * (cancelled) or warning (disputed).
 */
final readonly class OrderStep
{
    public function __construct(
        public string $key,
        public string $state,
        public ?CarbonImmutable $at,
    ) {}
}
