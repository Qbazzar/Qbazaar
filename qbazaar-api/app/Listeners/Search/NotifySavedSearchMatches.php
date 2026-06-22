<?php

declare(strict_types=1);

namespace App\Listeners\Search;

use App\Events\Ads\AdApproved;
use App\Events\Ads\AdPublished;
use App\Models\Ad;
use App\Models\SavedSearch;
use App\Notifications\Search\SavedSearchMatchNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Fan a freshly-published ad out to the owners of saved searches it matches.
 *
 * Queued: it scans every saved search, so it must never run inline on the
 * publish request. Saved searches are capped per user, so the total set stays
 * modest for the MVP — if it grows, move the match to an indexed/Meili pass.
 *
 * Matching is conservative: a criterion only constrains when it's present, and
 * a keyword (`q`) requires a substring hit on the title/description so a
 * keyword-only search can't match every new listing.
 */
class NotifySavedSearchMatches implements ShouldQueue
{
    public function handle(AdPublished|AdApproved $event): void
    {
        $ad = $event->ad->loadMissing(['category', 'location']);
        $sellerId = $ad->user_id;

        /** @var array<int, string> $notified */
        $notified = [];

        SavedSearch::query()
            ->with('user')
            ->chunkById(200, function ($searches) use ($ad, $sellerId, &$notified): void {
                foreach ($searches as $search) {
                    $user = $search->user;
                    // Skip the seller (own ad) and anyone already alerted by a
                    // different saved search this run.
                    if ($user === null || $user->id === $sellerId) {
                        continue;
                    }
                    if (in_array($user->id, $notified, true)) {
                        continue;
                    }

                    $params = is_array($search->query_params) ? $search->query_params : [];
                    if (! $this->matches($ad, $params)) {
                        continue;
                    }

                    $user->notify(new SavedSearchMatchNotification($ad, (string) $search->name));
                    $notified[] = $user->id;
                }
            });
    }

    /**
     * @param array<string, mixed> $p
     */
    private function matches(Ad $ad, array $p): bool
    {
        $category = $p['category_slug'] ?? null;
        if (is_string($category) && $category !== '' && $ad->category->slug !== $category) {
            return false;
        }

        $location = $p['location_slug'] ?? null;
        if (is_string($location) && $location !== '' && $ad->location->slug !== $location) {
            return false;
        }

        $price = $ad->price !== null ? (float) $ad->price : null;
        if (is_numeric($p['price_min'] ?? null) && ($price === null || $price < (float) $p['price_min'])) {
            return false;
        }
        if (is_numeric($p['price_max'] ?? null) && ($price === null || $price > (float) $p['price_max'])) {
            return false;
        }

        $condition = $p['condition'] ?? null;
        if (is_string($condition) && $condition !== '' && $ad->condition?->value !== $condition) {
            return false;
        }

        $q = $p['q'] ?? null;
        if (is_string($q) && trim($q) !== '') {
            $haystack = mb_strtolower($ad->title . ' ' . $ad->description);
            if (! str_contains($haystack, mb_strtolower(trim($q)))) {
                return false;
            }
        }

        return true;
    }
}
