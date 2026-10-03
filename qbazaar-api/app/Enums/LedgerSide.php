<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerSide: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
}
