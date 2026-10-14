<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerTransactionType;
use App\Support\Money;

/**
 * Folds grouped ledger movements (account, transaction type, debit, credit)
 * into an {@see IncomeStatement}. This is the one place that says which
 * movement is income, which is a deduction and which is a payout:
 *
 *  - revenue account, credit: income, by source;
 *  - revenue account, debit: refunds (commission given back, reversals), or
 *    gateway fees when a `gateway_fee` transaction ever debits it;
 *  - adjustments account, debit minus credit: credit the platform absorbed
 *    (admin corrections that give users money);
 *  - payouts-payable account, debit by `withdrawal_paid`: payouts released.
 */
final class IncomeFolder
{
    /** The transaction type a gateway fee will post with, once payments are live. */
    public const string GATEWAY_FEE_TYPE = 'gateway_fee';

    public static function gatewayFeesRecorded(): bool
    {
        return LedgerTransactionType::tryFrom(self::GATEWAY_FEE_TYPE) !== null;
    }

    /**
     * @param iterable<Movement> $rows
     * @param array<string, string> $accountTypes ledger account type by account id
     */
    public function fold(iterable $rows, array $accountTypes, string $gmv, int $orders): IncomeStatement
    {
        $income = IncomeStatement::empty()->income;
        $refunds = $absorbed = $payouts = $fees = Money::ZERO;

        foreach ($rows as $row) {
            $credit = Money::round((string) $row->credit);
            $debit = Money::round((string) $row->debit);
            $type = (string) $row->type;

            switch ($accountTypes[$row->accountId] ?? null) {
                case LedgerAccountType::PLATFORM_REVENUE->value:
                    $key = IncomeSource::forType($type)->value;
                    $income[$key] = Money::add($income[$key] ?? Money::ZERO, $credit);
                    $type === self::GATEWAY_FEE_TYPE ? $fees = Money::add($fees, $debit) : $refunds = Money::add($refunds, $debit);
                    break;
                case LedgerAccountType::PLATFORM_ADJUSTMENTS->value:
                    $absorbed = Money::add($absorbed, Money::subtract($debit, $credit));
                    break;
                case LedgerAccountType::PLATFORM_PAYOUTS_PAYABLE->value:
                    $payouts = $type === LedgerTransactionType::WITHDRAWAL_PAID->value ? Money::add($payouts, $debit) : $payouts;
                    break;
            }
        }

        return new IncomeStatement($gmv, $orders, $income, $refunds, self::gatewayFeesRecorded() ? $fees : null, $absorbed, $payouts);
    }
}
