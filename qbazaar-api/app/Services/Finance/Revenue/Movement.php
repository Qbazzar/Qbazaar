<?php

declare(strict_types=1);

namespace App\Services\Finance\Revenue;

use stdClass;

/**
 * Debit and credit one ledger account booked under one transaction type over
 * some span of time: the unit {@see IncomeFolder} folds into a statement.
 */
final readonly class Movement
{
    public function __construct(
        public string $accountId,
        public string $type,
        public string $debit,
        public string $credit,
    ) {}

    /**
     * @param string $prefix 'current_' or 'previous_' for rows that carry both periods, '' for a single one
     */
    public static function fromRow(stdClass $row, string $prefix = ''): self
    {
        return new self((string) $row->account_id, (string) $row->type, (string) $row->{$prefix . 'debit'}, (string) $row->{$prefix . 'credit'});
    }
}
