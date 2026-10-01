<?php

declare(strict_types=1);

namespace App\Actions\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Ads\AdLifecycleService;
use Illuminate\Support\Facades\DB;

/**
 * Applies a seller's partial update to an ad.
 *
 * A live ad whose reviewable content changes goes back to PENDING, otherwise
 * an approved listing could be swapped for prohibited content after review.
 * Price, condition and location edits stay live.
 */
class UpdateAdAction
{
    private const REVIEWABLE_ATTRIBUTES = [
        'title',
        'description',
        'category_id',
        'custom_fields',
    ];

    public function __construct(
        private readonly AdLifecycleService $lifecycle,
    ) {}

    /**
     * @param array<string, mixed> $attributes
     */
    public function __invoke(Ad $ad, array $attributes): Ad
    {
        return DB::transaction(function () use ($ad, $attributes): Ad {
            /** @var Ad $locked */
            $locked = Ad::query()->lockForUpdate()->findOrFail($ad->getKey());

            $locked->fill($attributes);

            $needsReview = $locked->status === AdStatus::ACTIVE
                && $locked->isDirty(self::REVIEWABLE_ATTRIBUTES);

            $locked->save();

            if ($needsReview) {
                $this->lifecycle->submitForReview($locked);
            }

            return $locked;
        });
    }
}
