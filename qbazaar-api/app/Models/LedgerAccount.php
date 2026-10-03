<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerOwnerType;
use App\Enums\LedgerSide;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One account in the chart of accounts. `balance` is kept in the account's
 * normal direction (a positive wallet means the platform owes the user) and
 * only LedgerService writes it, in the same database transaction as the
 * entries it summarises.
 *
 * @property string $id
 * @property string $code
 * @property LedgerOwnerType $owner_type
 * @property string|null $owner_id
 * @property LedgerAccountType $type
 * @property LedgerSide $normal_side
 * @property string $currency
 * @property string $balance
 * @property int $version
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LedgerAccount extends Model
{
    use HasUlids;

    protected $table = 'ledger_accounts';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_type' => LedgerOwnerType::class,
            'type' => LedgerAccountType::class,
            'normal_side' => LedgerSide::class,
            'balance' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Ledger balances change only through LedgerService.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Ledger accounts cannot be deleted.');
        });
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }
}
