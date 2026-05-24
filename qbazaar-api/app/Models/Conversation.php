<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * A buyer↔seller chat thread anchored to a single ad.
 *
 * Why snapshot `seller_id` rather than always reading `ad->user_id`?
 * Ads can change owner (admin re-assignment in Sprint 11). When that happens,
 * the historical conversations must stay attached to the original seller's
 * inbox — that's where the trust history lives.
 *
 * `last_message_at` / `last_message_preview` are denormalised on every send
 * so the inbox endpoint can `ORDER BY last_message_at DESC` with a single
 * index lookup. Sub-querying `messages` per-row would be O(N) on the inbox.
 *
 * @property string $id
 * @property string $ad_id
 * @property string $buyer_id
 * @property string $seller_id
 * @property Carbon|null $last_message_at
 * @property string|null $last_message_preview
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Ad $ad
 * @property User $buyer
 * @property User $seller
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, HasUlids;

    protected $table = 'conversations';

    /** @var string */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ad_id',
        'buyer_id',
        'seller_id',
        'last_message_at',
        'last_message_preview',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Relations
     * ──────────────────────────────────────────────────────────────────*/

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

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderByDesc('created_at');
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Participant helpers
     * ──────────────────────────────────────────────────────────────────*/

    /**
     * Ordered as [buyer_id, seller_id] so callers can `array_diff` against
     * the current user's id to pluck the other participant id deterministically.
     *
     * @return array<int, string>
     */
    public function participantIds(): array
    {
        return [$this->buyer_id, $this->seller_id];
    }

    public function isParticipant(User $user): bool
    {
        return $user->id === $this->buyer_id || $user->id === $this->seller_id;
    }

    /**
     * Given one participant, returns the other. Loads the lazy relation when
     * needed so callers can get away with a single Conversation::find() and
     * still receive a hydrated User.
     */
    public function otherParticipant(User $user): User
    {
        if ($user->id === $this->buyer_id) {
            return $this->seller;
        }

        if ($user->id === $this->seller_id) {
            return $this->buyer;
        }

        throw new RuntimeException(sprintf(
            'User %s is not a participant of conversation %s.',
            $user->id,
            $this->id,
        ));
    }

    /**
     * Id of the participant who is NOT $user. Cheap variant of
     * otherParticipant() that doesn't hydrate the relation — used by the
     * broadcasting channel routing where we only need the recipient's id.
     */
    public function otherParticipantId(User $user): string
    {
        return $user->id === $this->buyer_id ? $this->seller_id : $this->buyer_id;
    }

    /* ──────────────────────────────────────────────────────────────────
     *  Query scopes
     * ──────────────────────────────────────────────────────────────────*/

    /**
     * Conversations where $user is either buyer or seller.
     *
     * @param Builder<Conversation> $query
     * @return Builder<Conversation>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
        });
    }

    /**
     * Inbox ordering — most recent activity first, with a created_at tie-break
     * so brand-new (no messages yet) conversations still surface in a stable
     * order behind the active ones.
     *
     * @param Builder<Conversation> $query
     * @return Builder<Conversation>
     */
    public function scopeOrderedForInbox(Builder $query): Builder
    {
        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');
    }

    /**
     * Number of messages addressed to $user that they haven't read yet.
     *
     * Uses a dedicated index (conversation_id, sender_id, read_at) so the
     * count is a quick range scan even for chatty conversations.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }
}
