<?php

declare(strict_types=1);

namespace App\Data\Ledger;

final readonly class LockedWallet
{
    public function __construct(
        public string $wallet,
        public string $debt,
        public string $withdrawable,
    ) {}
}
