<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerOwnerType;
use App\Enums\LedgerSide;
use App\Support\Money;
use InvalidArgumentException;

/**
 * One side of a posting: an amount debited or credited to one account.
 */
final readonly class LedgerLine
{
    public string $amount;

    private function __construct(
        public LedgerAccountType $accountType,
        public ?string $ownerId,
        public LedgerSide $side,
        string $amount,
    ) {
        $this->amount = Money::of($amount);

        if (! Money::isPositive($this->amount)) {
            throw new InvalidArgumentException("A ledger line needs a positive amount, got [{$amount}].");
        }

        if (($accountType->ownerType() === LedgerOwnerType::USER) !== ($ownerId !== null)) {
            throw new InvalidArgumentException("Account [{$accountType->value}] and owner [{$ownerId}] do not match.");
        }
    }

    public static function debit(LedgerAccountType $accountType, string $amount, ?string $ownerId = null): self
    {
        return new self($accountType, $ownerId, LedgerSide::DEBIT, $amount);
    }

    public static function credit(LedgerAccountType $accountType, string $amount, ?string $ownerId = null): self
    {
        return new self($accountType, $ownerId, LedgerSide::CREDIT, $amount);
    }

    public function accountCode(): string
    {
        return $this->accountType->code($this->ownerId);
    }

    public function opposite(): self
    {
        $side = $this->side === LedgerSide::DEBIT ? LedgerSide::CREDIT : LedgerSide::DEBIT;

        return new self($this->accountType, $this->ownerId, $side, $this->amount);
    }
}
