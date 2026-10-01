<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\Ad;
use App\Models\Offer;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently erases a user and everything that belongs to them.
 *
 * Ads go through Eloquent one by one so the media library deletes their
 * files and Scout removes their search documents; a database cascade would
 * leave both behind. Offers are removed first because their foreign keys to
 * ads and users restrict deletes. Everything else (conversations, messages,
 * favorites, reviews, addresses, tokens, ...) cascades from the user row.
 *
 * Each step re-queries what is left, so a retry after a failure part-way
 * finishes the job instead of failing on rows that are already gone.
 */
class AccountEraser
{
    private const int AD_CHUNK = 100;

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

        DB::transaction(function () use ($user): void {
            OtpCode::query()->where('phone', $user->phone)->delete();
            $user->notifications()->delete();
            $user->tokens()->delete();
            $user->roles()->detach();
            $user->permissions()->detach();
            $user->forceDelete();
        });
    }
}
