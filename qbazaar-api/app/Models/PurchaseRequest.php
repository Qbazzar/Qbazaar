<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use App\Support\Money;
use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A buyer's "Buy Now" request at the ad's price, shown as a card in the
 * conversation. Only PurchaseRequestTransitionService changes `status`.
 *
 * `message_id` is the card bubble; `is_open` mirrors "status is pending" as
 * TRUE/NULL and backs the "one open request per buyer and ad" index.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $ad_id
 * @property string $buyer_id
 * @property string $seller_id
 * @property string|null $message_id
 * @property string|null $order_id
 * @property string $unit_price
 * @property int $quantity
 * @property string $currency
 * @property string|null $note
 * @property PurchaseRequestStatus $status
 * @property bool|null $is_open
 * @property string|null $cancelled_by
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Conversation $conversation
 * @property Ad|null $ad
 * @property User $buyer
 * @property User $seller
 */
class PurchaseRequest extends Model
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory, HasUlids;

    protected $table = 'purchase_requests';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'is_open' => 'boolean',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseRequest $request): void {
            $request->is_open = $request->status === PurchaseRequestStatus::PENDING ? true : null;
        });
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class)->withTrashed();
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

    public function total(): string
    {
        return Money::multiply($this->unit_price, $this->quantity);
    }

    public function isParticipant(User $user): bool
    {
        return $user->id === $this->buyer_id || $user->id === $this->seller_id;
    }
}
