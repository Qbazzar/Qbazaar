<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\Data\Ledger\ReconciliationReport;
use App\Enums\LedgerSide;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;

/**
 * Checks the ledger against itself:
 *   1. every account's cached balance equals the sum of its entries,
 *   2. every transaction's debits equal its credits,
 *   3. all debits ever posted equal all credits (the trial balance).
 *
 * Sums run in SQL, an account chunk at a time, so memory stays flat as the
 * ledger grows. Sums are compared after rounding to two decimals, which is
 * exact on MySQL decimals and absorbs float noise on SQLite in tests.
 */
class LedgerReconciler
{
    private const int ACCOUNT_CHUNK = 500;

    private const int MAX_REPORTED = 100;

    public function run(): ReconciliationReport
    {
        $totals = LedgerEntry::query()->toBase()
            ->selectRaw('COALESCE(SUM(debit), 0) as debits, COALESCE(SUM(credit), 0) as credits')
            ->first();

        return new ReconciliationReport(
            accountMismatches: $this->accountMismatches(),
            unbalancedTransactionIds: $this->unbalancedTransactionIds(),
            totalDebits: Money::round((string) ($totals->debits ?? 0)),
            totalCredits: Money::round((string) ($totals->credits ?? 0)),
        );
    }

    /**
     * @return list<array{account_id: string, code: string, stored: string, computed: string}>
     */
    private function accountMismatches(): array
    {
        $mismatches = [];

        LedgerAccount::query()
            ->select(['id', 'code', 'normal_side', 'balance'])
            ->chunkById(self::ACCOUNT_CHUNK, function (Collection $accounts) use (&$mismatches): void {
                $sums = LedgerEntry::query()->toBase()
                    ->whereIn('account_id', $accounts->modelKeys())
                    ->groupBy('account_id')
                    ->selectRaw('account_id, SUM(debit) as debits, SUM(credit) as credits')
                    ->get()
                    ->keyBy('account_id');

                foreach ($accounts as $account) {
                    /** @var LedgerAccount $account */
                    $sum = $sums->get($account->id);
                    $computed = $this->balanceFrom($account->normal_side, (string) ($sum->debits ?? 0), (string) ($sum->credits ?? 0));

                    if (Money::compare($computed, $account->balance) !== 0) {
                        $mismatches[] = ['account_id' => $account->id, 'code' => $account->code, 'stored' => $account->balance, 'computed' => $computed];
                    }
                }
            });

        return array_slice($mismatches, 0, self::MAX_REPORTED);
    }

    /**
     * @return list<string>
     */
    private function unbalancedTransactionIds(): array
    {
        /** @var list<string> */
        return LedgerEntry::query()->toBase()
            ->select('transaction_id')
            ->groupBy('transaction_id')
            ->havingRaw('ROUND(SUM(debit) - SUM(credit), 2) <> 0')
            ->limit(self::MAX_REPORTED)
            ->pluck('transaction_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    private function balanceFrom(LedgerSide $normalSide, string $debits, string $credits): string
    {
        $debits = Money::round($debits);
        $credits = Money::round($credits);

        return $normalSide === LedgerSide::DEBIT
            ? Money::subtract($debits, $credits)
            : Money::subtract($credits, $debits);
    }
}
