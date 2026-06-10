<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Services\Media\PerceptualHashService;
use Illuminate\Support\Facades\DB;

/**
 * Finds ACTIVE ads (other sellers only) whose images are perceptual
 * near-duplicates of the candidate ad's images.
 *
 * Distance is computed in PHP via PerceptualHashService so one code path
 * serves both MySQL (prod) and sqlite (tests) — sqlite lacks BIT_COUNT,
 * CONV and even a bitwise XOR operator. The candidate set (media of other
 * sellers' ACTIVE ads) is streamed in chunks to bound memory; at MVP
 * scale this is tens of milliseconds at publish time. If Pulse ever
 * flags it, move the comparison into MySQL (BIT_COUNT) or a BK-tree.
 */
class DuplicateImageDetector
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly PerceptualHashService $hasher,
    ) {}

    /**
     * @return list<string> ad ULIDs that contain a near-duplicate image
     */
    public function findDuplicateAdIds(Ad $ad): array
    {
        /** @var list<string> $candidateHashes */
        $candidateHashes = $ad->media()->whereNotNull('phash')->pluck('phash')->all();
        if ($candidateHashes === []) {
            return [];
        }

        $threshold = (int) config('qbazaar.moderation.phash_distance_threshold');

        /** @var array<string, true> $duplicateAdIds */
        $duplicateAdIds = [];

        DB::table('media')
            ->join('ads', 'ads.id', '=', 'media.model_id')
            ->where('media.model_type', $ad->getMorphClass())
            ->where('ads.status', AdStatus::ACTIVE->value)
            ->where('ads.user_id', '!=', $ad->user_id)
            ->whereNull('ads.deleted_at')
            ->whereNotNull('media.phash')
            ->select(['ads.id as ad_id', 'media.phash'])
            ->orderBy('media.id')
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($candidateHashes, $threshold, &$duplicateAdIds): void {
                foreach ($rows as $row) {
                    if (isset($duplicateAdIds[$row->ad_id])) {
                        continue;
                    }

                    foreach ($candidateHashes as $hash) {
                        if ($this->hasher->distance((string) $row->phash, $hash) <= $threshold) {
                            $duplicateAdIds[(string) $row->ad_id] = true;
                            break;
                        }
                    }
                }
            });

        // ULIDs contain letters so PHP keeps the array keys as strings, but
        // the explicit map pins the list<string> contract for static analysis.
        return array_map(static fn ($id): string => (string) $id, array_keys($duplicateAdIds));
    }
}
