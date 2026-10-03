<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Ledger rows are written once. A mistake is corrected with a reversal
 * transaction, never by editing or deleting history.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(static function (Model $model): never {
            throw new LogicException($model::class . ' rows are append-only and cannot be updated.');
        });

        static::deleting(static function (Model $model): never {
            throw new LogicException($model::class . ' rows are append-only and cannot be deleted.');
        });
    }
}
