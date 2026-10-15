<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use App\Enums\LedgerTransactionType;

/**
 * Where platform income comes from, named by the ledger transaction types
 * that credit the revenue account. A source whose types the ledger does not
 * have yet (subscriptions) is "not recorded": it lights up on its own once
 * the matching transaction types are added to {@see LedgerTransactionType}.
 */
enum IncomeSource: string
{
    case COMMISSION = 'commission';
    case PROMOTIONS = 'promotions';
    case SUBSCRIPTIONS = 'subscriptions';
    case OTHER = 'other';

    /**
     * @return list<string> ledger transaction types; empty for the catch-all
     */
    public function types(): array
    {
        return match ($this) {
            self::COMMISSION => [LedgerTransactionType::ESCROW_RELEASED->value, LedgerTransactionType::COMMISSION_CHARGED->value],
            self::PROMOTIONS => [LedgerTransactionType::PROMOTION_PURCHASED->value],
            self::SUBSCRIPTIONS => ['subscription_purchased'],
            self::OTHER => [],
        };
    }

    public function recorded(): bool
    {
        return array_reduce(
            $this->types(),
            static fn (bool $all, string $type): bool => $all && LedgerTransactionType::tryFrom($type) !== null,
            true,
        );
    }

    /**
     * The source a credit of this transaction type belongs to.
     */
    public static function forType(string $type): self
    {
        foreach ([self::COMMISSION, self::PROMOTIONS, self::SUBSCRIPTIONS] as $source) {
            if (in_array($type, $source->types(), true)) {
                return $source;
            }
        }

        return self::OTHER;
    }
}
