<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The business record a journal transaction belongs to.
 */
enum LedgerReferenceType: string
{
    case ORDER = 'order';
    case SETTLEMENT = 'settlement';
    case WITHDRAWAL = 'withdrawal';
    case PROMOTION = 'promotion';
    case ADJUSTMENT = 'adjustment';
}
