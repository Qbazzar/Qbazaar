<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromotionPaymentMethod;
use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use Database\Factories\AdPromotionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A paid promotion of one ad. Price and duration are frozen at purchase;
 * only PromotionLifecycleService changes `status`.
 *
 * @property string $id
 * @property string|null $ad_id
 * @property string|null $user_id
 * @property PromotionType $type
 * @property PromotionStatus $status
 * @property string|null $open_slot
 * @property PromotionPaymentMethod $payment_method
 * @property string $price
 * @property string $currency
 * @property int $duration_days
 * @property string|null $transfer_reference
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $reviewed_by
 * @property string|null $rejection_reason
 * @property Carbon|null $activated_at
 * @property Carbon|null $expired_at
 * @property Carbon|null $rejected_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Ad|null $ad
 * @property User|null $user
 */
class AdPromotion extends Model
{
    /** @use HasFactory<AdPromotionFactory> */
    use HasFactory, HasUlids;

    protected $table = 'ad_promotions';

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'status' => PromotionStatus::class,
            'payment_method' => PromotionPaymentMethod::class,
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'expired_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AdPromotion $promotion): void {
            $promotion->open_slot = $promotion->status->isOpen() && $promotion->ad_id !== null
                ? $promotion->ad_id . ':' . $promotion->type->value
                : null;
        });
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
