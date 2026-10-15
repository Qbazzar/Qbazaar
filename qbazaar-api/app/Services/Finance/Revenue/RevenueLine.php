<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use Carbon\CarbonImmutable;

/**
 * One posting on the revenue account: money the platform earned (credit) or
 * gave back (debit), with the record it came from.
 */
final readonly class RevenueLine
{
    public function __construct(
        public string $id,
        public CarbonImmutable $at,
        public string $type,
        public string $credit,
        public string $debit,
        public ?string $referenceType,
        public ?string $referenceId,
        public ?string $adId,
        public ?string $adTitle,
        public ?string $partyId,
        public ?string $partyName,
    ) {}
}
