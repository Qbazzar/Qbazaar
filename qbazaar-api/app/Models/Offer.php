<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OfferStatus;
use Database\Factories\OfferFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A buyer-initiated price offer attached to a conversation.
 *
 *  - `conversation_id` is the parent thread; `ad_id` + `buyer_id` +
 *    `seller_id` are denormalised from it so the active-offer invariant
 *    + the expiry sweeper can run on a single composite index without
 *    a join. See the migration's index notes for the access patterns.
 *  - `message_id` points at the chat bubble produced alongside the offer
 *    so the transcript can render the "I offer X" line inline. Nullable
 *    because deleting a message must not destroy the offer audit trail.
 *  - The lifecycle invariant is "PENDING is the only mutable state";
 *    {@see isActive()} bundles that with the expires_at check so callers
 *    don't have to re-compute it.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $ad_id
 * @property string $buyer_id
 * @property string $seller_id
 * @property string|null $message_id
 * @property string $amount
 * @property string $currency
 * @property string|null $note
 * @property OfferStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Conversation $conversation
 * @property Ad $ad
 * @property User $buyer
 * @property User $seller
 * @property Message|null $message
 */
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory, HasUlids;

    protected $table = 'offers';

    /** @var string */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'ad_id',
        'buyer_id',
        'seller_id',
        'message_id',
        'amount',
        'currency',
        'note',
        'status',
        'expires_at',
        'accepted_at',
        'rejected_at',
        'withdrawn_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Relations
     * ──────────────────────────────────────────────────────────────────*/

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Query scopes
     * ──────────────────────────────────────────────────────────────────*/

    /**
     * Pending-only — used both by the active-offer guard and as a guard
     * inside the accept / reject / withdraw transactions.
     *
     * @param Builder<Offer> $query
     * @return Builder<Offer>
     */
    public function scopePendingOnly(Builder $query): Builder
    {
        return $query->where('status', OfferStatus::PENDING->value);
    }

    /**
     * Pending offers whose expiry timestamp is on or before $t. Powers the
     * ExpireOldOffersJob sweep.
     *
     * @param Builder<Offer> $query
     * @return Builder<Offer>
     */
    public function scopeExpiredBy(Builder $query, DateTimeInterface $t): Builder
    {
        return $query
            ->where('status', OfferStatus::PENDING->value)
            ->where('expires_at', '<=', $t);
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Helpers
     * ──────────────────────────────────────────────────────────────────*/

    /**
     * An offer is "active" when it's still PENDING and its expiry window
     * hasn't closed yet. The expiry sweeper runs daily, so an offer can
     * legitimately be PENDING with `expires_at < now()` for up to 24h —
     * callers MUST consult this helper rather than the raw status.
     */
    public function isActive(): bool
    {
        return $this->status === OfferStatus::PENDING
            && $this->expires_at->isFuture();
    }
}
