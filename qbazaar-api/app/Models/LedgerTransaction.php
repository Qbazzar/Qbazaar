<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerActorType;
use App\Enums\LedgerReferenceType;
use App\Enums\LedgerTransactionType;
use App\Models\Builders\AppendOnlyBuilder;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One journal transaction: a balanced set of entries posted together.
 *
 * @property string $id
 * @property LedgerTransactionType $type
 * @property string $idempotency_key
 * @property LedgerReferenceType|null $reference_type
 * @property string|null $reference_id
 * @property string|null $memo
 * @property LedgerActorType $created_by_type
 * @property string|null $created_by_id
 * @property string|null $reversal_of
 * @property Carbon $posted_at
 * @property Carbon $created_at
 * @property-read Collection<int, LedgerEntry> $entries
 */
#[UseEloquentBuilder(AppendOnlyBuilder::class)]
class LedgerTransaction extends Model
{
    use AppendOnly, HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'ledger_transactions';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerTransactionType::class,
            'reference_type' => LedgerReferenceType::class,
            'created_by_type' => LedgerActorType::class,
            'posted_at' => 'datetime',
        ];
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'transaction_id');
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function reversed(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of');
    }

    /** @return HasOne<LedgerTransaction, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of');
    }
}
