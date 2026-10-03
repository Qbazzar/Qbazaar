<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WithdrawalStatus;
use App\Support\Iban;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A seller's request to be paid their wallet balance. The amount left the
 * wallet when it was requested; the admin then pays or rejects it.
 *
 * The holder and IBAN are copied from the bank account at request time,
 * so the payout destination cannot change after the seller asked for it.
 *
 * @property string $id
 * @property string|null $user_id
 * @property string|null $bank_account_id
 * @property WithdrawalStatus $status
 * @property string $amount
 * @property string $holder_name
 * @property string $iban
 * @property string $iban_last4
 * @property string|null $transfer_reference
 * @property string|null $rejection_reason
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $user
 * @property User|null $reviewer
 */
class Withdrawal extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['iban'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'amount' => 'decimal:2',
            'iban' => 'encrypted',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param Builder<Withdrawal> $query
     * @return Builder<Withdrawal>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function maskedIban(): string
    {
        return Iban::mask(substr($this->iban, 0, 2), $this->iban_last4);
    }
}
