<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\Ad;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puts an ad in or out of the user's favourites. Repeating either call is a
 * no-op: the (user_id, ad_id) unique key decides whether a row was really
 * added or removed, and `ads.favorites_count` only moves when it was, so
 * concurrent double taps can neither duplicate a row nor skew the counter.
 */
class SetFavoriteAction
{
    /**
     * @return array{favorited: bool, count: int}
     */
    public function execute(User $user, Ad $ad, bool $favorited): array
    {
        return DB::transaction(function () use ($user, $ad, $favorited): array {
            $favorited ? $this->add($user, $ad) : $this->remove($user, $ad);

            return [
                'favorited' => $favorited,
                'count' => (int) Ad::query()->whereKey($ad->id)->value('favorites_count'),
            ];
        });
    }

    private function add(User $user, Ad $ad): void
    {
        $alreadyFavorited = Favorite::query()->where('user_id', $user->id)->where('ad_id', $ad->id)->exists();

        if ($alreadyFavorited) {
            return;
        }

        if (Favorite::query()->where('user_id', $user->id)->count() >= (int) config('qbazaar.favorites.max_per_user')) {
            throw new DomainException(ErrorCode::FAV_LIMIT_REACHED);
        }

        $inserted = Favorite::query()->insertOrIgnore([
            'id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'ad_id' => $ad->id,
            'created_at' => now(),
        ]);

        if ($inserted > 0) {
            Ad::query()->whereKey($ad->id)->increment('favorites_count');
        }
    }

    private function remove(User $user, Ad $ad): void
    {
        $deleted = Favorite::query()->where('user_id', $user->id)->where('ad_id', $ad->id)->delete();

        if ($deleted > 0) {
            Ad::query()->whereKey($ad->id)->where('favorites_count', '>', 0)->decrement('favorites_count');
        }
    }
}
