<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Fulfillment;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A purchase between a buyer and a seller. Price, quantity and commission
 * are frozen at creation; only OrderTransitionService changes `status`.
 *
 * `active_ad_id` is kept equal to `ad_id` while the status is open and NULL
 * once it is closed; its unique index allows one open order per ad.
 *
 * @property string $id
 * @property string|null $ad_id
 * @property string|null $active_ad_id
 * @property string|null $buyer_id
 * @property string|null $seller_id
 * @property OrderSource $source
 * @property string $source_id
 * @property OrderStatus $status
 * @property string $ad_title
 * @property string $currency
 * @property string $unit_price
 * @property int $quantity
 * @property string $shipping_fee
 * @property string $total
 * @property string $commission_rate
 * @property string $commission_amount
 * @property PaymentMethod $payment_method
 * @property Fulfillment|null $fulfillment
 * @property array<string, string|null>|null $delivery_address
 * @property string|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $awaiting_handover_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $disputed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Ad|null $ad
 * @property User|null $buyer
 * @property User|null $seller
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUlids;

    protected $table = 'orders';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'fulfillment' => Fulfillment::class,
            'delivery_address' => 'array',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'awaiting_handover_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'disputed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            $order->active_ad_id = $order->status->isActive() ? $order->ad_id : null;
        });
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

    /**
     * @param Builder<Order> $query
     * @return Builder<Order>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', OrderStatus::activeValues());
    }

    public function isParticipant(User $user): bool
    {
        return $user->id === $this->buyer_id || $user->id === $this->seller_id;
    }
}
