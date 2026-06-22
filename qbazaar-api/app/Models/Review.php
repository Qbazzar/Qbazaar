<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A buyer's review of a seller after a deal.
 *
 * One per (reviewer, ad). Whenever a review is written or removed we recompute
 * the seller's denormalised rating_avg / rating_count so profile reads stay a
 * single cheap column lookup.
 *
 * @property string $id
 * @property string $ad_id
 * @property string $seller_id
 * @property string $reviewer_id
 * @property int $rating
 * @property string|null $comment
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Review extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ad_id',
        'seller_id',
        'reviewer_id',
        'rating',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(static fn (Review $review) => self::recalculate($review->seller_id));
        static::deleted(static fn (Review $review) => self::recalculate($review->seller_id));
    }

    /** @return BelongsTo<Ad, $this> */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Refresh the seller's denormalised rating aggregate from the reviews table.
     */
    public static function recalculate(string $sellerId): void
    {
        /** @var object{c: int, a: float|null} $agg */
        $agg = self::query()
            ->where('seller_id', $sellerId)
            ->selectRaw('COUNT(*) as c, AVG(rating) as a')
            ->first();

        User::query()->whereKey($sellerId)->update([
            'rating_count' => (int) ($agg->c ?? 0),
            'rating_avg' => round((float) ($agg->a ?? 0), 2),
        ]);
    }
}
