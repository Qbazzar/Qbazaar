<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use App\Support\Money;

final readonly class ReconciliationReport
{
    /**
     * @param list<array{account_id: string, code: string, stored: string, computed: string}> $accountMismatches
     * @param list<string> $unbalancedTransactionIds
     * @param list<string> $malformedEntryIds entries without exactly one positive side
     */
    public function __construct(
        public array $accountMismatches,
        public array $unbalancedTransactionIds,
        public array $malformedEntryIds,
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
            && $this->malformedEntryIds === []
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
            'malformed_entry_ids' => $this->malformedEntryIds,
            'total_debits' => $this->totalDebits,
            'total_credits' => $this->totalCredits,
        ];
    }
}
