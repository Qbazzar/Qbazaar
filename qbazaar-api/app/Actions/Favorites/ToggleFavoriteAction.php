<?php

declare(strict_types=1);

namespace App\Actions\Favorites;

use App\Models\Ad;
use App\Models\Favorite;
use App\Models\User;

/**
 * Flips the user's favourite for an ad (the original `POST /ads/{id}/favorite`).
 * Clients that may retry should prefer the idempotent PUT / DELETE pair.
 */
class ToggleFavoriteAction
{
    public function __construct(private readonly SetFavoriteAction $setFavorite) {}

    /**
     * @return array{favorited: bool, count: int}
     */
    public function execute(User $user, Ad $ad): array
    {
        $isFavorited = Favorite::query()->where('user_id', $user->id)->where('ad_id', $ad->id)->exists();

        return $this->setFavorite->execute($user, $ad, ! $isFavorited);
    }
}
