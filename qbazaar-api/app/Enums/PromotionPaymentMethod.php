<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionPaymentMethod: string
{
    /** Deducted from the seller's wallet; the promotion starts at once. */
    case WALLET = 'wallet';

    /** Paid into the platform's bank account; starts when an admin confirms it. */
    case BANK_TRANSFER = 'bank_transfer';
}
