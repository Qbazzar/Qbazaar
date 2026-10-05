<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a seller pays their commission debt: a bank transfer the admin
 * confirms, or netting it from their own wallet balance.
 */
enum SettlementMethod: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case WALLET = 'wallet';
}
