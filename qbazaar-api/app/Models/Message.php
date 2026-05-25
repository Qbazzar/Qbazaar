<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageType;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Individual chat line inside a conversation.
 *
 *  - `type` distinguishes plain user chat (`text`) from inline offer cards
 *    (`offer`, Sprint 9) and moderation / system notices (`system`).
 *  - `read_at` is per-message. We don't keep a `read_status` enum because
 *    a single timestamp is enough — null means unread, any value means
 *    read at that moment.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $sender_id
 * @property string $body
 * @property MessageType $type
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Conversation $conversation
 * @property User $sender
 * @property Offer|null $offer
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, HasUlids;

    protected $table = 'messages';

    /** @var string */
    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'type',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'read_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * The Offer this message introduces (only set for `type=offer`
     * bubbles). HasOne because `offers.message_id` is the FK; declared
     * here so transcript responses can `with('offer')` and surface the
     * full offer payload inline without an N+1 fan-out.
     *
     * @return HasOne<Offer, $this>
     */
    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class, 'message_id');
    }
}
