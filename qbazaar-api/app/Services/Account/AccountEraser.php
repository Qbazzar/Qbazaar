<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\Ad;
use App\Models\DataExport;
use App\Models\Favorite;
use App\Models\Offer;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Users\FollowGraph;
use Illuminate\Support\Facades\DB;

/**
 * Permanently erases a user and everything that belongs to them.
 *
 * Ads go through Eloquent one by one so the media library deletes their
 * files and Scout removes their search documents; a database cascade would
 * leave both behind. Offers are removed first because their foreign keys to
 * ads and users restrict deletes. Follows and favorites are removed here so
 * the other side's denormalised counters drop with them. Everything else
 * (conversations and their hide flags, messages, reviews, addresses, saved
 * searches, business profile, trusted devices, social logins, tokens, ...)
 * cascades from the user row.
 *
 * Each step re-queries what is left, so a retry after a failure part-way
 * finishes the job instead of failing on rows that are already gone.
 */
class AccountEraser
{
    private const int AD_CHUNK = 100;

    public function __construct(
        private readonly FollowGraph $follows,
    ) {}

    public function erase(User $user): void
    {
        Offer::query()
            ->where(fn ($query) => $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->delete();

        Ad::withTrashed()
            ->where('user_id', $user->id)
            ->lazyById(self::AD_CHUNK)
            ->each(fn (Ad $ad) => $ad->forceDelete());

        $user->clearMediaCollection('avatar');
        $user->clearMediaCollection(User::BUSINESS_COVER_COLLECTION);

        DataExport::query()->where('user_id', $user->id)->each(fn (DataExport $export) => $export->deleteFile());

        DB::transaction(function () use ($user): void {
            $this->follows->detachAll($user);
            $this->removeFavorites($user);
            OtpCode::query()->whereIn('recipient', [$user->phone, $user->email])->delete();
            $user->notifications()->delete();
            $user->tokens()->delete();
            $user->roles()->detach();
            $user->permissions()->detach();
            $user->forceDelete();
        });
    }

    /**
     * The rows would cascade away with the user, but the favourited ads'
     * counters would not, so lower them in one set-based update first.
     */
    private function removeFavorites(User $user): void
    {
        Ad::query()->toBase()
            ->whereIn('id', Favorite::query()->select('ad_id')->where('user_id', $user->id))
            ->where('favorites_count', '>', 0)
            ->decrement('favorites_count');

        Favorite::query()->where('user_id', $user->id)->delete();
    }
}
