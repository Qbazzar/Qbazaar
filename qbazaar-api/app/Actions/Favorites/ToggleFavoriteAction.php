<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Models\Ad;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Toggle a user's favourite status for an ad.
 *
 *  - Wrapped in a transaction so the row insert/delete and the
 *    denormalised `ads.favorites_count` mutation always agree.
 *  - The schema-level unique constraint on (user_id, ad_id) protects
 *    against double-favourite races: a duplicate insert would raise a
 *    constraint violation rolling back the counter increment. We still
 *    probe with a SELECT first to choose the branch, but never rely on
 *    that probe being authoritative under concurrency.
 *  - Returns the post-toggle state so the caller can render the heart
 *    icon + count immediately without a follow-up read.
 */
class ToggleFavoriteAction
{
    /**
     * @return array{favorited: bool, count: int}
     */
    public function execute(User $user, Ad $ad): array
    {
        return DB::transaction(function () use ($user, $ad): array {
            /** @var Favorite|null $existing */
            $existing = Favorite::query()
                ->where('user_id', $user->id)
                ->where('ad_id', $ad->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->delete();
                // Floor at 0 — the counter is denormalised and a stray
                // delete should never push it negative.
                Ad::query()
                    ->where('id', $ad->id)
                    ->where('favorites_count', '>', 0)
                    ->decrement('favorites_count');

                $favorited = false;
            } else {
                Favorite::query()->create([
                    'user_id' => $user->id,
                    'ad_id' => $ad->id,
                ]);
                Ad::query()->where('id', $ad->id)->increment('favorites_count');

                $favorited = true;
            }

            $count = (int) Ad::query()
                ->where('id', $ad->id)
                ->value('favorites_count');

            return [
                'favorited' => $favorited,
                'count' => $count,
            ];
        });
    }
}
