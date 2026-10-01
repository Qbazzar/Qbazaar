<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Models\Ad;
use App\Models\Favorite;
use App\Models\User;

/**
 * Resolves `is_favorited` for a page of ads with a single query on the
 * (user_id, ad_id) unique index, instead of one lookup per card.
 */
class ViewerFavorites
{
    /**
     * Sets `is_favorited` on each ad; guests are left untouched and read as false.
     *
     * @param iterable<Ad> $ads
     */
    public function mark(?User $viewer, iterable $ads): void
    {
        $ads = is_array($ads) ? $ads : iterator_to_array($ads, false);

        if ($viewer === null || $ads === []) {
            return;
        }

        $favorited = $this->favoritedIds($viewer, array_map(static fn (Ad $ad): string => $ad->id, $ads));

        foreach ($ads as $ad) {
            // Synced as original so the flag never counts as a dirty column on a later save.
            $ad->forceFill(['is_favorited' => isset($favorited[$ad->id])])->syncOriginalAttribute('is_favorited');
        }
    }

    /**
     * Same as {@see mark()} for ad cards that are already serialised, such as
     * a payload cached once for every visitor.
     *
     * @param list<array<string, mixed>> $cards
     * @return list<array<string, mixed>>
     */
    public function overlay(?User $viewer, array $cards): array
    {
        if ($viewer === null || $cards === []) {
            return $cards;
        }

        $favorited = $this->favoritedIds($viewer, array_map(static fn (array $card): string => (string) $card['id'], $cards));

        return array_map(
            static fn (array $card): array => array_replace($card, ['is_favorited' => isset($favorited[(string) $card['id']])]),
            $cards,
        );
    }

    /**
     * @param array<string> $adIds
     * @return array<string, true>
     */
    private function favoritedIds(User $viewer, array $adIds): array
    {
        /** @var list<string> $ids */
        $ids = Favorite::query()
            ->where('user_id', $viewer->id)
            ->whereIn('ad_id', array_values(array_unique($adIds)))
            ->pluck('ad_id')
            ->all();

        return array_fill_keys($ids, true);
    }
}
