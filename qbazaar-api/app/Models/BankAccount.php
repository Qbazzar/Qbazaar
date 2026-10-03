<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Iban;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A bank account a seller is paid out to. Only BankAccountService writes
 * it; the IBAN is encrypted at rest and never leaves the API unmasked.
 *
 * @property string $id
 * @property string $user_id
 * @property string $holder_name
 * @property string $iban
 * @property string $iban_hash
 * @property string $iban_last4
 * @property string|null $bank_name
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BankAccount extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['iban', 'iban_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'iban' => 'encrypted',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param Builder<BankAccount> $query
     * @return Builder<BankAccount>
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
