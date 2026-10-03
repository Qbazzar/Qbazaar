<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\ErrorCode;

/**
 * The chart of accounts, seen from the platform's books.
 *
 * Asset accounts (money the platform holds or is owed) grow on the debit
 * side; liability and revenue accounts (money the platform owes or has
 * earned) grow on the credit side. A user's wallet is therefore a liability:
 * it is money the platform owes that user.
 */
enum LedgerAccountType: string
{
    /** Commission and promotion income. */
    case PLATFORM_REVENUE = 'revenue';

    /** Buyer money held until handover (electronic or bank payments only). */
    case PLATFORM_ESCROW = 'escrow';

    /** The platform's bank account. */
    case PLATFORM_BANK = 'bank';

    /** Approved withdrawals not yet paid out of the bank. */
    case PLATFORM_PAYOUTS_PAYABLE = 'payouts_payable';

    /** Money a payment gateway has collected and not yet settled to the bank. */
    case PLATFORM_GATEWAY_CLEARING = 'gateway_clearing';

    /** The other side of manual corrections approved by an admin. */
    case PLATFORM_ADJUSTMENTS = 'adjustments';

    /** What the platform owes the user: sale proceeds they can withdraw or spend. */
    case USER_WALLET = 'wallet';

    /** What the user owes the platform: commission on cash sales. */
    case USER_COMMISSION_RECEIVABLE = 'commission_receivable';

    public function ownerType(): LedgerOwnerType
    {
        return match ($this) {
            self::USER_WALLET, self::USER_COMMISSION_RECEIVABLE => LedgerOwnerType::USER,
            default => LedgerOwnerType::PLATFORM,
        };
    }

    public function normalSide(): LedgerSide
    {
        return match ($this) {
            self::PLATFORM_BANK,
            self::PLATFORM_GATEWAY_CLEARING,
            self::PLATFORM_ADJUSTMENTS,
            self::USER_COMMISSION_RECEIVABLE => LedgerSide::DEBIT,

            self::PLATFORM_REVENUE,
            self::PLATFORM_ESCROW,
            self::PLATFORM_PAYOUTS_PAYABLE,
            self::USER_WALLET => LedgerSide::CREDIT,
        };
    }

    /**
     * Accounts that can never hold less than zero. A user cannot spend more
     * than their wallet or pay off more debt than they owe, and the platform
     * can never hold negative escrow or owe a negative payout.
     */
    public function mayGoNegative(): bool
    {
        return match ($this) {
            self::USER_WALLET,
            self::USER_COMMISSION_RECEIVABLE,
            self::PLATFORM_ESCROW,
            self::PLATFORM_PAYOUTS_PAYABLE => false,
            default => true,
        };
    }

    /**
     * The error a client sees when a posting would overdraw this account, or
     * null when only a bug could cause it.
     */
    public function overdraftError(): ?ErrorCode
    {
        return match ($this) {
            self::USER_WALLET => ErrorCode::WALLET_INSUFFICIENT_BALANCE,
            self::USER_COMMISSION_RECEIVABLE => ErrorCode::WALLET_EXCEEDS_COMMISSION_DEBT,
            default => null,
        };
    }

    public function code(?string $ownerId = null): string
    {
        return $this->ownerType() === LedgerOwnerType::USER
            ? "user:{$ownerId}:{$this->value}"
            : "platform:{$this->value}";
    }
}
