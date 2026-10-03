<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One posting line: exactly one of `debit` and `credit` is positive.
 * `balance_after` is the account balance right after this line, which gives
 * every statement a running balance without summing history.
 *
 * @property string $id
 * @property string $transaction_id
 * @property string $account_id
 * @property string $debit
 * @property string $credit
 * @property string $balance_after
 * @property Carbon $created_at
 * @property LedgerTransaction $transaction
 * @property LedgerAccount $account
 */
class LedgerEntry extends Model
{
    use AppendOnly, HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'ledger_entries';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }
}
