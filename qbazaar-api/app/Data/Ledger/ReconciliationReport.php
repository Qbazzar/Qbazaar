<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use App\Support\Money;

final readonly class ReconciliationReport
{
    /**
     * @param list<array{account_id: string, code: string, stored: string, computed: string}> $accountMismatches
     * @param list<string> $unbalancedTransactionIds
     */
    public function __construct(
        public array $accountMismatches,
        public array $unbalancedTransactionIds,
        public string $totalDebits,
        public string $totalCredits,
    ) {}

    public function trialBalanceHolds(): bool
    {
        return Money::compare($this->totalDebits, $this->totalCredits) === 0;
    }

    public function isClean(): bool
    {
        return $this->accountMismatches === []
            && $this->unbalancedTransactionIds === []
            && $this->trialBalanceHolds();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_mismatches' => $this->accountMismatches,
            'unbalanced_transaction_ids' => $this->unbalancedTransactionIds,
            'total_debits' => $this->totalDebits,
            'total_credits' => $this->totalCredits,
        ];
    }
}
