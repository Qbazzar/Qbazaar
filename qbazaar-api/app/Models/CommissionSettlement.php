<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettlementMethod;
use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A seller paying off their commission debt. The ledger posting is made
 * when it is approved; this row is the request and its review.
 *
 * `pending_user_id` is kept equal to `user_id` while pending and NULL
 * otherwise; its unique index allows one pending settlement per seller.
 *
 * @property string $id
 * @property string|null $user_id
 * @property string|null $pending_user_id
 * @property SettlementMethod $method
 * @property SettlementStatus $status
 * @property string $amount
 * @property string|null $bank_reference
 * @property string|null $rejection_reason
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User|null $user
 * @property User|null $reviewer
 */
class CommissionSettlement extends Model implements HasMedia
{
    use HasUlids, InteractsWithMedia;

    public const string PROOF_COLLECTION = 'proof';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => SettlementMethod::class,
            'status' => SettlementStatus::class,
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CommissionSettlement $settlement): void {
            $settlement->pending_user_id = $settlement->status === SettlementStatus::PENDING ? $settlement->user_id : null;
        });
    }

    /**
     * The transfer receipt is a bank document: it stays on a private disk
     * and only finance staff open it, through an authorized admin route.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PROOF_COLLECTION)
            ->singleFile()
            ->useDisk((string) config('qbazaar.wallet.proof_disk'));
    }

    public function proof(): ?Media
    {
        return $this->getFirstMedia(self::PROOF_COLLECTION);
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
     * @param Builder<CommissionSettlement> $query
     * @return Builder<CommissionSettlement>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
