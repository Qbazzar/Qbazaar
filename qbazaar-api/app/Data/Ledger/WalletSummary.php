<?php

declare(strict_types=1);

namespace App\Data\Ledger;

final readonly class WalletSummary
{
    public function __construct(
        public string $available,
        public string $commissionDebt,
        public string $debtCeiling,
        public bool $canAcceptOrders,
    ) {}
}
