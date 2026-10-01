<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Catalog\CategoryHierarchy;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finds other sellers' live ads whose images are perceptual near-duplicates
 * of the candidate ad's images.
 *
 * The Hamming distance is computed in SQL on `media.phash_int`, so no hash
 * rows reach PHP. A distance cannot use a B-tree, so the scan is bounded
 * from the ads side instead: recent ACTIVE ads in the same root category
 * (ads_category_status_published_idx), then each ad's hashes straight from
 * media_model_phash_idx.
 */
class DuplicateImageDetector
{
    public function __construct(
        private readonly CategoryHierarchy $categories,
    ) {}

    /**
     * @return list<string> ad ULIDs that contain a near-duplicate image
     */
    public function findDuplicateAdIds(Ad $ad): array
    {
        /** @var list<int> $hashes */
        $hashes = $ad->media()
            ->whereNotNull('phash_int')
            ->distinct()
            ->pluck('phash_int')
            ->map(static fn (mixed $hash): int => (int) $hash)
            ->all();

        if ($hashes === []) {
            return [];
        }

        $query = DB::table('ads')
            ->join('media', function ($join) use ($ad): void {
                $join->on('media.model_id', '=', 'ads.id')
                    ->where('media.model_type', '=', $ad->getMorphClass());
            })
            ->where('ads.status', AdStatus::ACTIVE->value)
            ->where('ads.published_at', '>=', now()->subDays((int) config('moderation.duplicate_images.window_days')))
            ->where('ads.user_id', '!=', $ad->user_id)
            ->whereNull('ads.deleted_at')
            ->whereNotNull('media.phash_int')
            ->where(fn (Builder $query) => $this->whereNearAnyHash($query, $hashes));

        $categoryIds = $this->sameRootCategoryIds($ad);

        if ($categoryIds !== null) {
            $query->whereIn('ads.category_id', $categoryIds);
        }

        return array_values($query->distinct()
            ->limit((int) config('moderation.duplicate_images.max_matches'))
            ->pluck('ads.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all());
    }

    /**
     * XOR is spelled (a | b) & ~(a & b) because SQLite, used by the test
     * suite, has no XOR operator; MySQL evaluates both forms the same way.
     *
     * @param list<int> $hashes
     */
    private function whereNearAnyHash(Builder $query, array $hashes): void
    {
        $threshold = (int) config('qbazaar.moderation.phash_distance_threshold');

        foreach ($hashes as $hash) {
            $query->orWhereRaw(
                'BIT_COUNT((media.phash_int | ?) & ~(media.phash_int & ?)) <= ?',
                [$hash, $hash, $threshold],
            );
        }
    }

    /**
     * @return list<string>|null null when the check should not narrow by category
     */
    private function sameRootCategoryIds(Ad $ad): ?array
    {
        if (! (bool) config('moderation.duplicate_images.same_root_category') || $ad->category_id === null) {
            return null;
        }

        $root = $this->categories->pathTo($ad->category_id)[0];

        return $this->categories->descendantsOf($root);
    }
}
